<?php
$activities = $navData['activities'] ?? [];
$newProducts = $navData['new_products'] ?? [];
$courses = $navData['recommended_courses'] ?? [];
?>
<section class="space-y-8 animate-rise">
    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="rounded-[2rem] border border-bronze/20 bg-white/75 p-6 shadow-glow backdrop-blur">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <p class="text-sm uppercase tracking-[0.35em] text-bronze/70">Magic Entry</p>
                    <h1 class="mt-2 font-display text-4xl text-bronze sm:text-5xl">欢迎来到奇妙世界入口</h1>
                </div>
                <div class="hidden h-20 w-20 rounded-full border border-bronze/20 bg-parchment/80 sm:flex sm:items-center sm:justify-center sm:text-bronze sm:shadow-card">妙</div>
            </div>
            <p class="max-w-2xl text-sm leading-7 text-ink/70 sm:text-base">
                导航页延续商城的穆夏风格，作为微信网页入口聚合奇妙集市、知识星球、热门活动、商品上新和推荐课程。
                管理员可在后台维护活动内容、推荐商品与课程展示位。
            </p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <a href="/mall" class="group rounded-[1.6rem] border border-bronze/25 bg-parchment/80 p-5 shadow-card transition duration-300 hover:-translate-y-1 hover:border-bronze hover:shadow-glow">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-bronze/10 text-2xl text-bronze">市</div>
                    <h2 class="font-display text-2xl text-ink">奇妙集市</h2>
                    <p class="mt-2 text-sm text-ink/65">进入电商商城，浏览商品、课程、会员折扣与购物流程。</p>
                </a>
                <a href="https://magic.lyccc.xyz/login" target="_blank" rel="noreferrer" class="group rounded-[1.6rem] border border-teal/25 bg-white/85 p-5 shadow-card transition duration-300 hover:-translate-y-1 hover:border-teal hover:shadow-glow">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-teal/10 text-2xl text-teal">课</div>
                    <h2 class="font-display text-2xl text-ink">知识星球</h2>
                    <p class="mt-2 text-sm text-ink/65">跳转到课程网站，继续学习与会员内容相关的扩展知识。</p>
                </a>
            </div>
        </div>

        <div class="rounded-[2rem] border border-sage/20 bg-white/75 p-6 shadow-card backdrop-blur">
            <p class="text-sm uppercase tracking-[0.35em] text-sage/80">微信网页能力</p>
            <ul class="mt-5 space-y-4 text-sm leading-7 text-ink/70">
                <li>支持微信 OAuth 绑定，登录后可获取并绑定用户 OpenID。</li>
                <li>支持公众号模板消息通知管理员和普通用户。</li>
                <li>支持微信支付参数配置、配置测试与支付异步通知处理。</li>
            </ul>
            <div class="mt-6 rounded-[1.5rem] bg-gradient-to-br from-rose/10 via-transparent to-sage/10 p-5 text-sm text-ink/70">
                未登录用户可以浏览页面，但购物车、下单、地址管理、绑定微信、支付等功能需要登录后使用。
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-2xl text-bronze">热门活动</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-bronze/60">Hot</span>
            </div>
            <div class="space-y-4">
                <?php foreach ($activities as $activity): ?>
                    <article class="rounded-[1.4rem] border border-bronze/10 bg-parchment/70 p-4">
                        <div class="text-sm font-semibold text-ink"><?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?></div>
                        <p class="mt-2 text-sm leading-6 text-ink/65"><?= htmlspecialchars($activity['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-[1.8rem] border border-sage/15 bg-white/80 p-5 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-2xl text-sage">商品上新</h2>
                <a href="/mall" class="text-xs uppercase tracking-[0.3em] text-sage/70">进入商城</a>
            </div>
            <div class="space-y-4">
                <?php foreach ($newProducts as $product): ?>
                    <a href="/mall/products/<?= (int) $product['id'] ?>" class="flex items-center gap-3 rounded-[1.4rem] border border-sage/10 bg-sage/5 p-4 transition hover:bg-sage/10">
                        <div class="h-16 w-16 rounded-2xl bg-cover bg-center" style="background-image:url('<?= htmlspecialchars($product['cover_image'], ENT_QUOTES, 'UTF-8') ?>')"></div>
                        <div class="min-w-0">
                            <div class="truncate font-medium text-ink"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="mt-1 text-sm text-ink/60">¥<?= money_format_cn($product['price']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-[1.8rem] border border-teal/15 bg-white/80 p-5 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-2xl text-teal">推荐课程</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-teal/70">Course</span>
            </div>
            <div class="space-y-4">
                <?php foreach ($courses as $course): ?>
                    <a href="/mall/products/<?= (int) $course['id'] ?>" class="block rounded-[1.4rem] border border-teal/10 bg-teal/5 p-4 transition hover:bg-teal/10">
                        <div class="font-medium text-ink"><?= htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <p class="mt-2 text-sm leading-6 text-ink/60"><?= htmlspecialchars($course['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
