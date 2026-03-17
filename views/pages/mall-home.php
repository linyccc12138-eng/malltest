<?php $homeData = $homeSections ?? []; ?>
<script>window.PAGE_DATA = <?= json_encode_unicode(['home' => $homeData]) ?>;</script>
<section x-data="mallHomePage()" class="space-y-8 animate-rise">
    <div class="grid gap-4 lg:grid-cols-[1.3fr_0.9fr] lg:auto-rows-fr">
        <div class="relative h-full overflow-hidden rounded-[2rem] border border-bronze/20 bg-white/75 p-4 shadow-glow backdrop-blur sm:p-5">
            <div class="absolute -right-16 top-0 h-28 w-28 rounded-full bg-rose/10 blur-3xl"></div>
            <div class="absolute left-4 top-4 h-16 w-16 rounded-full border border-bronze/10 bg-bronze/5"></div>
            <div class="relative ml-auto flex h-full max-w-2xl flex-col justify-center">
                <p class="text-sm uppercase tracking-[0.35em] text-bronze/70">WeChat Mall</p>
                <h1 class="mt-3 font-display text-3xl leading-tight text-ink sm:text-4xl">穆夏风格的会员联动商城，兼顾商品、课程与移动端下单体验</h1>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
            <div class="h-full rounded-[1.6rem] border border-sage/15 bg-white/75 p-4 shadow-card">
                <div class="text-sm uppercase tracking-[0.3em] text-sage/70">会员权益</div>
                <div class="mt-2 font-display text-xl text-ink"><?= $currentMember ? htmlspecialchars($currentMember['fclassesname'], ENT_QUOTES, 'UTF-8') : '未绑定会员' ?></div>
                <p class="mt-2 text-sm text-ink/65">支持实时读取会员等级、余额与折扣。商品可单独配置是否参与会员折扣。</p>
            </div>
        </div>
    </div>

    <section id="catalog" class="grid gap-6 lg:grid-cols-[280px_1fr]">
        <aside class="space-y-4 rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <div>
                <div class="text-sm uppercase tracking-[0.3em] text-bronze/70">商品筛选</div>
                <h2 class="mt-2 font-display text-2xl text-ink">按关键词、品牌与价格快速筛选</h2>
            </div>

            <label class="block text-sm text-ink/70">
                关键词
                <input x-model="filters.keyword" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/50" placeholder="输入商品名或副标题">
            </label>

            <label class="block text-sm text-ink/70">
                品牌
                <select x-model="filters.brand" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/50">
                    <option value="">全部品牌</option>
                    <template x-for="brand in filterOptions.brands" :key="brand">
                        <option :value="brand" x-text="brand"></option>
                    </template>
                </select>
            </label>

            <label class="block text-sm text-ink/70">
                排序
                <select x-model="filters.sort" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/50">
                    <option value="newest">按上新时间</option>
                    <option value="sales">按销量</option>
                    <option value="price_asc">价格从低到高</option>
                    <option value="price_desc">价格从高到低</option>
                </select>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm text-ink/70">
                    最低价
                    <input x-model="filters.price_min" type="number" min="0" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/50">
                </label>
                <label class="block text-sm text-ink/70">
                    最高价
                    <input x-model="filters.price_max" type="number" min="0" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/50">
                </label>
            </div>

            <div class="space-y-2">
                <div class="rounded-[1.2rem] border border-bronze/10 bg-parchment/45 px-4 py-3 text-xs leading-6 text-ink/55">
                    输入或切换条件后会自动筛选商品。
                </div>
                <button @click="resetFilters" type="button" class="w-full rounded-full border border-bronze/15 px-4 py-3 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">重置条件</button>
            </div>
        </aside>

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm uppercase tracking-[0.3em] text-bronze/70">商品列表</div>
                    <h2 class="font-display text-3xl text-ink">兼顾移动端与桌面端的商城浏览体验</h2>
                </div>
                <button x-show="products.meta.has_more" @click="loadMore" class="rounded-full border border-bronze/20 px-4 py-2 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">加载更多</button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <template x-for="item in products.data" :key="item.id">
                    <article class="group rounded-[1.6rem] border border-bronze/12 bg-white/85 p-4 shadow-card transition duration-300 hover:-translate-y-1 hover:shadow-glow">
                        <a :href="'/mall/products/' + item.id" class="block overflow-hidden rounded-[1.4rem] bg-parchment/70">
                            <div class="product-cover h-52 bg-cover bg-center transition duration-500 group-hover:scale-[1.04]" :style="`background-image:url(${item.cover_image})`"></div>
                        </a>
                        <div class="mt-4 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-ink" x-text="item.name"></h3>
                                <p class="mt-1 line-clamp-2 text-sm text-ink/60" x-text="item.summary"></p>
                            </div>
                            <span class="rounded-full bg-sage/10 px-3 py-1 text-xs text-sage" x-text="item.brand || '无品牌'"></span>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <div>
                                <div class="text-lg font-semibold text-bronze">￥<span x-text="formatMoney(item.price)"></span></div>
                                <div class="text-xs text-ink/45">评分 <span x-text="item.rating"></span> / 库存 <span x-text="item.stock_total"></span></div>
                            </div>
                            <button @click="openQuickView(item.id)" class="rounded-full border border-bronze/20 px-4 py-2 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">Quick View</button>
                        </div>
                    </article>
                </template>
            </div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-[1.8rem] border border-sage/15 bg-white/80 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-2xl text-sage">商品上新</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-sage/70">New</span>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?php foreach (($homeData['new_arrivals'] ?? []) as $item): ?>
                    <a href="/mall/products/<?= (int) $item['id'] ?>" class="rounded-[1.4rem] border border-sage/10 bg-sage/5 p-4 transition hover:bg-sage/10">
                        <div class="text-sm text-sage/70">新品推荐</div>
                        <div class="mt-2 font-medium text-ink"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="mt-1 text-sm text-ink/55"><?= htmlspecialchars($item['summary'], ENT_QUOTES, 'UTF-8') ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="rounded-[1.8rem] border border-teal/15 bg-white/80 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-2xl text-teal">推荐课程</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-teal/70">Course</span>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?php foreach (($homeData['recommended_courses'] ?? []) as $item): ?>
                    <a href="/mall/products/<?= (int) $item['id'] ?>" class="rounded-[1.4rem] border border-teal/10 bg-teal/5 p-4 transition hover:bg-teal/10">
                        <div class="text-sm text-teal/70">课程型商品</div>
                        <div class="mt-2 font-medium text-ink"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="mt-1 text-sm text-ink/55"><?= htmlspecialchars($item['summary'], ENT_QUOTES, 'UTF-8') ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <template x-teleport="body">
        <div x-show="quickView.open" x-cloak x-transition.opacity.duration.180ms class="modal-overlay" @click.self="quickView.open = false">
            <div x-transition.scale.opacity.duration.220ms class="modal-panel w-full max-w-2xl overflow-y-auto rounded-[2rem] border border-bronze/10 bg-white/95 p-6 shadow-glow">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm uppercase tracking-[0.3em] text-bronze/65">Quick View</div>
                        <h3 class="mt-2 font-display text-3xl text-ink" x-text="quickView.data?.name || ''"></h3>
                    </div>
                    <button @click="quickView.open = false" class="rounded-full border border-bronze/20 px-3 py-2 text-sm text-bronze">关闭</button>
                </div>
                <p class="mt-4 text-sm leading-7 text-ink/65" x-text="quickView.data?.quick_view_text || ''"></p>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <template x-for="sku in quickView.data?.skus || []" :key="sku.id">
                        <div class="rounded-[1.3rem] border border-bronze/10 bg-parchment/55 p-4">
                            <div class="font-medium text-ink" x-text="Object.values(sku.attributes || {}).join(' / ') || sku.sku_code"></div>
                            <div class="mt-2 text-sm text-ink/60">价格 ￥<span x-text="formatMoney(sku.price)"></span> / 库存 <span x-text="sku.stock"></span></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</section>
