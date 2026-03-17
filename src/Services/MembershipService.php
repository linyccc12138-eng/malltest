<?php

declare(strict_types=1);

namespace Mall\Services;

use Mall\Core\DatabaseManager;
use Mall\Core\Logger;
use PDO;

class MembershipService
{
    private ?PDO $customMembershipPdo = null;

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Logger $logger,
        private readonly SettingsService $settings
    ) {
    }

    public function getClasses(): array
    {
        $stmt = $this->membershipPdo()->query('SELECT fid, fname, foff FROM classes ORDER BY fid ASC');
        return $stmt->fetchAll();
    }

    public function searchMembers(string $keyword = '', int $page = 1, int $pageSize = 15): array
    {
        $pdo = $this->membershipPdo();
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;

        if ($keyword === '') {
            $countStmt = $pdo->query('SELECT COUNT(*) AS total FROM member');
            $total = (int) (($countStmt->fetch()['total'] ?? 0));
            $stmt = $pdo->prepare(
                'SELECT fid, fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark
                 FROM member
                 ORDER BY fid DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'items' => $stmt->fetchAll(),
                'meta' => [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => $total,
                    'total_pages' => max(1, (int) ceil($total / $pageSize)),
                ],
            ];
        }

        $searchSql = 'fnumber LIKE :keyword_number OR fname LIKE :keyword_name';
        $params = [
            ':keyword_number' => '%' . $keyword . '%',
            ':keyword_name' => '%' . $keyword . '%',
        ];

        if (ctype_digit($keyword)) {
            $searchSql = 'fid = :fid OR ' . $searchSql;
            $params[':fid'] = (int) $keyword;
        }

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM member
             WHERE ' . $searchSql
        );
        $countStmt->execute($params);
        $total = (int) (($countStmt->fetch()['total'] ?? 0));

        $stmt = $pdo->prepare(
            'SELECT fid, fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark
             FROM member
             WHERE ' . $searchSql . '
             ORDER BY fid DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':keyword_number', '%' . $keyword . '%');
        $stmt->bindValue(':keyword_name', '%' . $keyword . '%');
        if (ctype_digit($keyword)) {
            $stmt->bindValue(':fid', (int) $keyword, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $pageSize)),
            ],
        ];
    }

    public function getMemberById(int $memberId): ?array
    {
        $stmt = $this->membershipPdo()->prepare(
            'SELECT m.fid, m.fnumber, m.fname, m.fclassesid, m.fclassesname, m.faccruedamount, m.fbalance, m.fmark, c.foff
             FROM member m
             LEFT JOIN classes c ON c.fid = m.fclassesid
             WHERE m.fid = :fid'
        );
        $stmt->execute([':fid' => $memberId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getMallUserMember(int $userId): ?array
    {
        $stmt = $this->db->mall()->prepare('SELECT membership_member_id FROM mall_users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['membership_member_id'])) {
            return null;
        }

        return $this->getMemberById((int) $row['membership_member_id']);
    }

    public function getDiscountRateByMallUser(int $userId): float
    {
        $member = $this->getMallUserMember($userId);
        if (!$member) {
            return 1.0;
        }

        $raw = (float) ($member['foff'] ?? 1.0);
        if ($raw > 1 && $raw <= 10) {
            $raw = $raw / 10;
        }

        if ($raw <= 0 || $raw > 1) {
            return 1.0;
        }

        return round($raw, 2);
    }

    public function createMember(array $data): array
    {
        $pdo = $this->membershipPdo();
        $pdo->beginTransaction();

        try {
            $fnumber = trim((string) ($data['fnumber'] ?? ''));
            $fname = trim((string) ($data['fname'] ?? ''));
            if ($fnumber === '' || $fname === '') {
                throw new \RuntimeException('会员编号和会员名称不能为空。');
            }

            $exists = $pdo->prepare('SELECT fid FROM member WHERE fnumber = :fnumber');
            $exists->execute([':fnumber' => $fnumber]);
            if ($exists->fetch()) {
                throw new \RuntimeException('会员编号已存在。');
            }

            $classId = (int) ($data['fclassesid'] ?? 0);
            $className = trim((string) ($data['fclassesname'] ?? ''));
            if ($classId > 0 && $className === '') {
                $className = $this->findClassName($classId);
            }

            $amount = round((float) ($data['initial_amount'] ?? 0), 2);
            if ($amount < 0) {
                throw new \RuntimeException('初始金额不能小于 0。');
            }

            $remark = trim((string) ($data['fmark'] ?? ''));

            $insert = $pdo->prepare(
                'INSERT INTO member (fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark)
                 VALUES (:fnumber, :fname, :fclassesid, :fclassesname, :faccruedamount, :fbalance, :fmark)'
            );
            $insert->execute([
                ':fnumber' => $fnumber,
                ':fname' => $fname,
                ':fclassesid' => $classId,
                ':fclassesname' => $className,
                ':faccruedamount' => $amount,
                ':fbalance' => $amount,
                ':fmark' => $remark,
            ]);

            $memberId = (int) $pdo->lastInsertId();
            $this->insertMemberLogs(
                $pdo,
                '新增会员',
                $memberId,
                $fname,
                $classId,
                $className,
                $amount,
                $amount,
                $remark,
                ''
            );

            $pdo->commit();
            $this->logger->info('membership', '会员创建成功', ['member_id' => $memberId]);
            return $this->getMemberById($memberId) ?? [];
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function updateMember(int $memberId, array $data): array
    {
        $pdo = $this->membershipPdo();
        $pdo->beginTransaction();

        try {
            $existing = $this->getMemberById($memberId);
            if (!$existing) {
                throw new \RuntimeException('会员不存在。');
            }

            $fnumber = trim((string) ($data['fnumber'] ?? $existing['fnumber']));
            $fname = trim((string) ($data['fname'] ?? $existing['fname']));
            if ($fnumber === '' || $fname === '') {
                throw new \RuntimeException('会员编号和会员名称不能为空。');
            }

            $duplicate = $pdo->prepare('SELECT fid FROM member WHERE fnumber = :fnumber AND fid <> :fid');
            $duplicate->execute([':fnumber' => $fnumber, ':fid' => $memberId]);
            if ($duplicate->fetch()) {
                throw new \RuntimeException('会员编号已被其他会员占用。');
            }

            $classId = (int) ($data['fclassesid'] ?? $existing['fclassesid']);
            $className = trim((string) ($data['fclassesname'] ?? $existing['fclassesname'] ?? ''));
            if ($classId > 0 && $className === '') {
                $className = $this->findClassName($classId);
            }

            $balance = round((float) ($data['fbalance'] ?? $existing['fbalance']), 2);
            if ($balance < 0) {
                throw new \RuntimeException('会员余额不能小于 0。');
            }

            $remark = trim((string) ($data['fmark'] ?? $existing['fmark'] ?? ''));

            $update = $pdo->prepare(
                'UPDATE member
                 SET fnumber = :fnumber, fname = :fname, fclassesid = :fclassesid, fclassesname = :fclassesname, fbalance = :fbalance, fmark = :fmark
                 WHERE fid = :fid'
            );
            $update->execute([
                ':fnumber' => $fnumber,
                ':fname' => $fname,
                ':fclassesid' => $classId,
                ':fclassesname' => $className,
                ':fbalance' => $balance,
                ':fmark' => $remark,
                ':fid' => $memberId,
            ]);

            $this->insertMemberLogs(
                $pdo,
                '编辑会员',
                $memberId,
                $fname,
                $classId,
                $className,
                0,
                $balance,
                $remark,
                ''
            );

            $pdo->commit();
            $this->logger->info('membership', '会员信息已更新', ['member_id' => $memberId]);
            return $this->getMemberById($memberId) ?? [];
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function adjustBalance(int $memberId, float $amount, string $mark, string $goods = ''): array
    {
        $pdo = $this->membershipPdo();
        $pdo->beginTransaction();

        try {
            if (round($amount, 2) === 0.0) {
                throw new \RuntimeException('调整金额不能为 0。');
            }

            $member = $this->getLockedMember($pdo, $memberId);
            if (!$member) {
                throw new \RuntimeException('会员不存在。');
            }

            $mode = $amount > 0 ? '充值' : '消费';
            $absolute = round(abs($amount), 2);
            $newBalance = $mode === '充值'
                ? round((float) $member['fbalance'] + $absolute, 2)
                : round((float) $member['fbalance'] - $absolute, 2);

            if ($newBalance < 0) {
                throw new \RuntimeException('会员余额不足。');
            }

            $sql = $mode === '充值'
                ? 'UPDATE member SET faccruedamount = faccruedamount + :amount, fbalance = :fbalance WHERE fid = :fid'
                : 'UPDATE member SET fbalance = :fbalance WHERE fid = :fid';

            $stmt = $pdo->prepare($sql);
            $params = [
                ':fbalance' => $newBalance,
                ':fid' => $memberId,
            ];
            if ($mode === '充值') {
                $params[':amount'] = $absolute;
            }
            $stmt->execute($params);

            $this->insertMemberLogs(
                $pdo,
                $mode,
                $memberId,
                (string) $member['fname'],
                (int) $member['fclassesid'],
                (string) $member['fclassesname'],
                $absolute,
                $newBalance,
                $mark,
                $goods
            );

            $pdo->commit();
            $this->logger->info('membership', '会员余额已调整', [
                'member_id' => $memberId,
                'mode' => $mode,
                'amount' => $absolute,
                'balance' => $newBalance,
            ]);

            return $this->getMemberById($memberId) ?? [];
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function consumeForOrder(int $memberId, string $goodsNames, float $amount, string $mark = '电商网站自助下单'): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('消费金额必须大于 0。');
        }

        return $this->adjustBalance($memberId, -$amount, $mark, $goodsNames);
    }

    public function compensate(int $memberId, float $amount, string $reason, string $goodsNames = ''): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('补偿金额必须大于 0。');
        }

        return $this->adjustBalance($memberId, $amount, $reason, $goodsNames);
    }

    private function membershipPdo(): PDO
    {
        if ($this->customMembershipPdo instanceof PDO) {
            return $this->customMembershipPdo;
        }

        $config = $this->settings->getGroup('membership_mysql');
        if (!empty($config['host']) && !empty($config['database']) && !empty($config['username'])) {
            $this->customMembershipPdo = $this->settings->buildPdo([
                'host' => $config['host'],
                'port' => $config['port'] ?? '3306',
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'] ?? '',
                'charset' => $config['charset'] ?? 'utf8mb4',
            ]);

            return $this->customMembershipPdo;
        }

        return $this->db->membership();
    }

    private function getLockedMember(PDO $pdo, int $memberId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT fid, fname, fclassesid, fclassesname, faccruedamount, fbalance
             FROM member
             WHERE fid = :fid
             FOR UPDATE'
        );
        $stmt->execute([':fid' => $memberId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findClassName(int $classId): string
    {
        $stmt = $this->membershipPdo()->prepare('SELECT fname FROM classes WHERE fid = :fid LIMIT 1');
        $stmt->execute([':fid' => $classId]);
        $row = $stmt->fetch();
        return (string) ($row['fname'] ?? '');
    }

    private function insertMemberLogs(
        PDO $pdo,
        string $mode,
        int $memberId,
        string $memberName,
        int $classId,
        string $className,
        float $amount,
        float $balance,
        string $mark,
        string $goods = ''
    ): void {
        $sql = 'INSERT INTO %s (fdate, fmode, fmemberid, fmembername, fclassesid, fclassesname, fgoods, famount, fbalance, fmark)
                VALUES (:fdate, :fmode, :fmemberid, :fmembername, :fclassesid, :fclassesname, :fgoods, :famount, :fbalance, :fmark)';

        foreach (['menmberdetail', 'menmberdetail_log'] as $table) {
            $stmt = $pdo->prepare(sprintf($sql, $table));
            $stmt->execute([
                ':fdate' => now(),
                ':fmode' => $mode,
                ':fmemberid' => $memberId,
                ':fmembername' => $memberName,
                ':fclassesid' => $classId,
                ':fclassesname' => $className,
                ':fgoods' => $goods,
                ':famount' => $amount,
                ':fbalance' => $balance,
                ':fmark' => $mark,
            ]);
        }
    }
}
