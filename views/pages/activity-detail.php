<?php $activity = $activity ?? []; ?>
<section class="mx-auto max-w-4xl space-y-6 animate-rise">
    <div class="overflow-hidden rounded-[2rem] border border-bronze/15 bg-white/85 shadow-glow">
        <?php if (!empty($activity['thumbnail_image'])): ?>
            <div class="aspect-[16/8] w-full overflow-hidden bg-parchment/60">
                <img src="<?= htmlspecialchars((string) $activity['thumbnail_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="h-full w-full object-cover">
            </div>
        <?php endif; ?>
        <div class="p-5 sm:p-7">
            <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">活动详情</div>
            <h1 class="mt-3 font-display text-3xl leading-tight text-ink sm:text-4xl"><?= htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if (!empty($activity['summary'])): ?>
                <p class="mt-3 text-sm leading-7 text-ink/65 sm:text-base"><?= htmlspecialchars((string) $activity['summary'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <div class="mt-4 flex flex-wrap gap-3 text-xs text-ink/50 sm:text-sm">
                <?php if (!empty($activity['starts_at'])): ?>
                    <span>开始时间 <?= htmlspecialchars((string) $activity['starts_at'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if (!empty($activity['ends_at'])): ?>
                    <span>结束时间 <?= htmlspecialchars((string) $activity['ends_at'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <article class="rich-content-body prose prose-stone max-w-none rounded-[2rem] border border-bronze/15 bg-white/85 p-5 shadow-card sm:p-7">
        <?= $activity['content_html'] ?: '<p>暂无活动详情。</p>' ?>
    </article>
</section>
