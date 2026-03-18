<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php
    $shareMeta = is_array($shareMeta ?? null) ? $shareMeta : [];
    $metaTitle = (string) ($shareMeta['title'] ?? ($pageTitle ?? $appName));
    $metaDescription = trim((string) ($shareMeta['description'] ?? '奇妙集市微信网页商城，支持会员联动、余额支付、微信支付、用户中心与管理后台。'));
    $metaUrl = (string) ($shareMeta['url'] ?? '');
    $metaImage = (string) ($shareMeta['image'] ?? '');
    $metaType = (string) ($shareMeta['type'] ?? 'website');
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars(($pageTitle ?? $appName) . ' - ' . $appName, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($metaUrl !== ''): ?>
        <link rel="canonical" href="<?= htmlspecialchars($metaUrl, ENT_QUOTES, 'UTF-8') ?>">
        <meta property="og:url" content="<?= htmlspecialchars($metaUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= htmlspecialchars((string) $appName, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($metaType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($metaImage !== ''): ?>
        <meta property="og:image" content="<?= htmlspecialchars($metaImage, ENT_QUOTES, 'UTF-8') ?>">
        <meta property="og:image:secure_url" content="<?= htmlspecialchars($metaImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= $metaImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($metaImage !== ''): ?>
        <meta name="twitter:image" content="<?= htmlspecialchars($metaImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        parchment: '#f7efe3',
                        ink: '#2f2419',
                        bronze: '#9c6737',
                        sage: '#758a73',
                        teal: '#3c6d71',
                        rose: '#c98778',
                        sand: '#e7d7c4'
                    },
                    fontFamily: {
                        display: ['Georgia', 'STKaiti', 'KaiTi', 'serif'],
                        body: ['Microsoft YaHei', 'PingFang SC', 'sans-serif']
                    },
                    boxShadow: {
                        card: '0 20px 45px rgba(82, 58, 34, 0.12)',
                        glow: '0 0 0 1px rgba(156, 103, 55, 0.18), 0 18px 40px rgba(60, 109, 113, 0.1)'
                    },
                    backgroundImage: {
                        aura: 'radial-gradient(circle at top, rgba(201,135,120,0.18), transparent 35%), radial-gradient(circle at 80% 10%, rgba(117,138,115,0.16), transparent 28%), linear-gradient(180deg, #fbf6ef 0%, #f6efe3 100%)'
                    },
                    animation: {
                        floaty: 'floaty 6s ease-in-out infinite',
                        rise: 'rise 0.6s ease-out both'
                    },
                    keyframes: {
                        floaty: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-8px)' }
                        },
                        rise: {
                            '0%': { opacity: 0, transform: 'translateY(16px)' },
                            '100%': { opacity: 1, transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="icon" href="<?= asset('images/favicon.svg') ?>" type="image/svg+xml">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-aura font-body text-ink">
    <?php $currentUser = $currentUser ?? null; ?>
    <div class="fixed inset-x-0 top-0 z-0 h-72 bg-[radial-gradient(circle_at_top,_rgba(201,135,120,0.2),_transparent_45%)]"></div>

    <header class="sticky top-0 z-40 border-b border-bronze/15 bg-parchment/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-3 py-2 md:px-4 md:py-3 lg:px-6">
            <div class="flex items-center gap-2.5 md:gap-3">
                <button type="button" data-nav-back class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-bronze/25 bg-white/70 text-base text-bronze shadow-card md:hidden" aria-label="返回">
                    <span aria-hidden="true">‹</span>
                </button>
                <a href="/portal" class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full border border-bronze/40 bg-white/70 shadow-card md:h-11 md:w-11">
                        <span class="text-sm font-bold text-bronze md:text-lg">妙</span>
                    </div>
                    <div>
                        <div class="font-display text-base tracking-[0.16em] text-bronze md:text-lg md:tracking-[0.2em]">奇妙集市</div>
                        <div class="text-[10px] text-ink/60 md:text-xs">会员联动电商网站</div>
                    </div>
                </a>
            </div>

            <nav class="hidden items-center gap-5 text-sm text-ink/70 md:flex">
                <a class="hover:text-bronze" href="/portal">导航页</a>
                <a class="hover:text-bronze" href="/mall">商城首页</a>
                <a class="hover:text-bronze" href="/mall/profile">用户中心</a>
                <a class="hover:text-bronze" href="/mall/cart">购物车</a>
                <?php if ($currentUser && ($currentUser['role'] ?? '') === 'admin'): ?>
                    <a class="hover:text-bronze" href="/mall/admin">管理后台</a>
                <?php endif; ?>
            </nav>

            <div class="flex items-center gap-1.5 md:gap-2">
                <?php if ($currentUser): ?>
                    <a href="/mall/profile" class="rounded-full border border-bronze/25 px-2.5 py-1.5 text-xs text-ink transition hover:border-bronze hover:text-bronze md:px-3 md:py-2 md:text-sm">
                        <?= htmlspecialchars($currentUser['nickname'] ?? $currentUser['username'] ?? '用户', ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <button type="button" data-logout class="rounded-full border border-rose/25 px-3 py-1.5 text-xs text-ink transition hover:border-rose hover:text-rose md:px-4 md:py-2 md:text-sm">
                        退出
                    </button>
                <?php else: ?>
                    <a href="/mall/login" class="rounded-full bg-bronze px-3 py-1.5 text-xs text-white shadow-card transition hover:bg-bronze/90 md:px-4 md:py-2 md:text-sm">登录</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if (!empty($flashSuccess)): ?>
        <div class="mx-auto mt-4 max-w-7xl px-4 lg:px-6">
            <div class="rounded-2xl border border-sage/30 bg-sage/10 px-4 py-3 text-sm text-sage"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="mx-auto mt-4 max-w-7xl px-4 lg:px-6">
            <div class="rounded-2xl border border-rose/30 bg-rose/10 px-4 py-3 text-sm text-rose"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <main class="relative z-10 mx-auto max-w-7xl px-4 py-6 lg:px-6 lg:py-8">
        <?= $content ?>
    </main>

    <footer class="border-t border-bronze/10 bg-white/60">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm text-ink/60 lg:flex-row lg:items-center lg:justify-between lg:px-6">
            <div>奇妙集市 · 微信网页商城 · 会员系统联动</div>
            <div>支持游客浏览，登录后可使用购物车、下单、地址管理、余额支付与微信绑定功能。</div>
        </div>
    </footer>

    <script>
        window.MALL_BOOTSTRAP = <?= json_encode_unicode([
            'pageKey' => $pageKey ?? '',
            'csrfToken' => $csrfToken ?? '',
            'currentUser' => $currentUser,
            'currentMember' => $currentMember ?? null,
            'appName' => $appName ?? '奇妙集市',
        ]) ?>;
    </script>
    <button type="button" data-back-to-top class="back-top-button" aria-label="返回顶部">↑</button>
    <a href="/mall/cart" data-floating-cart class="floating-cart-button" aria-label="打开购物车">
        <img src="<?= asset('images/icon-cart.svg') ?>" alt="" class="h-5 w-5">
        <span data-floating-cart-count class="floating-cart-badge">0</span>
    </a>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
