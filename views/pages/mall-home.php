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

    <section id="catalog" class="space-y-4 lg:grid lg:grid-cols-[280px_1fr] lg:gap-6 lg:space-y-0">
        <div
            class="flex items-center justify-between border border-bronze/12 bg-white/80 px-4 py-3 shadow-card transition-all lg:hidden"
            :class="mobileFiltersOpen ? 'rounded-t-[1.5rem] rounded-b-none border-b-transparent shadow-none' : 'rounded-[1.5rem]'"
        >
            <div>
                <div class="text-sm uppercase tracking-[0.3em] text-bronze/70">商品筛选</div>
                <div class="text-sm text-ink/60">按关键词、品牌和分类快速筛选</div>
            </div>
            <button @click="mobileFiltersOpen = !mobileFiltersOpen" type="button" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">
                <span x-text="mobileFiltersOpen ? '收起' : '展开'"></span>
            </button>
        </div>

        <aside
            x-show="mobileFiltersOpen || window.innerWidth >= 1024"
            x-transition.opacity.duration.180ms
            class="space-y-4 rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card"
            :class="mobileFiltersOpen ? 'mt-0 rounded-t-none border-t-0 pt-4' : 'mt-4 lg:mt-0'"
            x-cloak
        >
            <div>
                <div class="text-sm uppercase tracking-[0.3em] text-bronze/70">商品筛选</div>
            </div>

            <label class="flex items-center gap-3 text-sm text-ink/70 sm:block">
                <span class="w-14 shrink-0 text-sm sm:w-auto">关键词</span>
                <input x-model="filters.keyword" type="text" class="flex-1 rounded-2xl border-bronze/15 bg-parchment/50 sm:mt-2 sm:w-full" placeholder="输入商品名或简介">
            </label>

            <label class="flex items-center gap-3 text-sm text-ink/70 sm:block">
                <span class="w-14 shrink-0 text-sm sm:w-auto">品牌</span>
                <select x-model="filters.brand" class="flex-1 rounded-2xl border-bronze/15 bg-parchment/50 sm:mt-2 sm:w-full">
                    <option value="">全部品牌</option>
                    <template x-for="brand in filterOptions.brands" :key="brand">
                        <option :value="brand" x-text="brand"></option>
                    </template>
                </select>
            </label>

            <label class="flex items-center gap-3 text-sm text-ink/70 sm:block">
                <span class="w-14 shrink-0 text-sm sm:w-auto">分类</span>
                <select x-model="filters.category_id" class="flex-1 rounded-2xl border-bronze/15 bg-parchment/50 sm:mt-2 sm:w-full">
                    <option value="">全部分类</option>
                    <template x-for="item in categoryOptions" :key="item.id">
                        <option :value="String(item.id)" x-text="categoryLabel(item)"></option>
                    </template>
                </select>
            </label>

            <label class="flex items-center gap-3 text-sm text-ink/70 sm:block">
                <span class="w-14 shrink-0 text-sm sm:w-auto">排序</span>
                <select x-model="filters.sort" class="flex-1 rounded-2xl border-bronze/15 bg-parchment/50 sm:mt-2 sm:w-full">
                    <option value="newest">按上新时间</option>
                    <option value="sales">按销量</option>
                    <option value="price_asc">价格从低到高</option>
                    <option value="price_desc">价格从高到低</option>
                </select>
            </label>

            <button @click="resetFilters" type="button" class="w-full rounded-full border border-bronze/15 px-4 py-3 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">重置条件</button>
        </aside>

        <div class="space-y-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm uppercase tracking-[0.3em] text-bronze/70">商品列表</div>
                </div>
                <button x-show="products.meta.has_more" @click="loadMore" class="rounded-full border border-bronze/20 px-4 py-2 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">加载更多</button>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:gap-4 xl:grid-cols-3">
                <template x-for="item in products.data" :key="item.id">
                    <article class="group rounded-[10px] border border-bronze/12 bg-white/85 p-3 shadow-card transition duration-300 hover:-translate-y-1 hover:shadow-glow sm:p-4">
                        <a :href="'/mall/products/' + item.id" class="block overflow-hidden rounded-[10px] bg-parchment/70">
                            <img :src="item.cover_image || ''" :alt="item.name || ''" class="mall-square-media transition duration-500 group-hover:scale-[1.04]">
                        </a>
                        <div class="mt-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-ink" x-text="item.name"></h3>
                                <p class="mt-1 min-h-[2.5rem] line-clamp-2 text-xs text-ink/60 sm:min-h-[3rem] sm:text-sm" x-text="item.summary || ''"></p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-end justify-between gap-2">
                            <div>
                                <div class="text-base font-semibold text-bronze sm:text-lg">￥<span x-text="formatMoney(item.price)"></span></div>
                                <div class="text-[11px] text-ink/45 sm:text-xs">评分 <span x-text="item.rating"></span> / 库存 <span x-text="item.stock_total"></span></div>
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button @click="openQuickView(item.id)" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-bronze text-white shadow-card transition hover:bg-bronze/90" aria-label="加入购物车">
                                <img src="<?= asset('images/icon-cart.svg') ?>" alt="" class="h-4 w-4">
                            </button>
                            <a :href="'/mall/products/' + item.id" class="rounded-full border border-bronze/20 px-3 py-2 text-xs text-bronze transition hover:border-bronze hover:bg-bronze/5 sm:text-sm">详情</a>
                        </div>
                    </article>
                </template>
            </div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-[1.8rem] border border-sage/15 bg-white/80 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-2xl text-sage">商品上新</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-sage/70">New</span>
            </div>
            <div class="mt-4 space-y-4">
                <?php foreach (($homeData['new_arrivals'] ?? []) as $item): ?>
                    <a href="/mall/products/<?= (int) $item['id'] ?>" class="flex items-center gap-3 rounded-[1.4rem] border border-sage/10 bg-sage/5 p-4 transition hover:bg-sage/10">
                        <div class="h-20 w-20 rounded-[1.2rem] bg-cover bg-center" style="background-image:url('<?= htmlspecialchars($item['cover_image'], ENT_QUOTES, 'UTF-8') ?>')"></div>
                        <div class="min-w-0">
                            <div class="text-sm text-sage/70">新品推荐</div>
                            <div class="mt-1 font-medium text-ink"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="mt-1 line-clamp-2 text-sm text-ink/55"><?= htmlspecialchars($item['summary'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-2xl text-bronze">热门活动</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-bronze/70">Hot</span>
            </div>
            <div class="mt-4 space-y-4">
                <?php foreach (($homeData['hot_activities'] ?? []) as $activity): ?>
                    <article class="flex items-center gap-3 rounded-[1.4rem] border border-bronze/10 bg-parchment/65 p-4">
                        <div class="h-20 w-20 overflow-hidden rounded-[1.2rem] border border-bronze/10 bg-white">
                            <?php if (!empty($activity['thumbnail_image'])): ?>
                                <img src="<?= htmlspecialchars($activity['thumbnail_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?>" class="h-full w-full object-cover">
                            <?php else: ?>
                                <div class="flex h-full w-full items-center justify-center text-xs text-ink/40">暂无缩略图</div>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <div class="font-medium text-ink"><?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <p class="mt-1 line-clamp-2 text-sm leading-6 text-ink/60"><?= htmlspecialchars($activity['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="rounded-[1.8rem] border border-teal/15 bg-white/80 p-5 shadow-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-2xl text-teal">推荐课程</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-teal/70">Course</span>
            </div>
            <div class="mt-4 space-y-4">
                <?php foreach (($homeData['recommended_courses'] ?? []) as $item): ?>
                    <a href="/mall/products/<?= (int) $item['id'] ?>" class="flex items-center gap-3 rounded-[1.4rem] border border-teal/10 bg-teal/5 p-4 transition hover:bg-teal/10">
                        <div class="h-20 w-20 rounded-[1.2rem] bg-cover bg-center" style="background-image:url('<?= htmlspecialchars($item['cover_image'], ENT_QUOTES, 'UTF-8') ?>')"></div>
                        <div class="min-w-0">
                            <div class="text-sm text-teal/70">课程型商品</div>
                            <div class="mt-1 font-medium text-ink"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="mt-1 line-clamp-2 text-sm text-ink/55"><?= htmlspecialchars($item['summary'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <template x-teleport="body">
        <div x-show="quickView.open" x-cloak x-transition.opacity.duration.180ms class="modal-overlay" @click.self="quickView.open = false">
            <div x-transition.scale.opacity.duration.220ms class="mall-quick-view-panel modal-panel w-full max-w-3xl overflow-y-auto rounded-[1.5rem] border border-bronze/10 bg-white/95 p-4 shadow-glow sm:rounded-[2rem] sm:p-6">
                <div class="flex items-start justify-between gap-3 sm:gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.28em] text-bronze/65 sm:text-sm sm:tracking-[0.3em]">加入购物车</div>
                        <h3 class="mt-1.5 font-display text-2xl text-ink sm:mt-2 sm:text-3xl" x-text="quickView.data?.name || ''"></h3>
                        <p class="mt-1.5 text-xs leading-6 text-ink/60 sm:mt-2 sm:text-sm sm:leading-7" x-text="quickView.data?.summary || quickView.data?.quick_view_text || ''"></p>
                    </div>
                    <button @click="quickView.open = false" type="button" class="rounded-full border border-bronze/20 px-3 py-1.5 text-xs text-bronze sm:px-3 sm:py-2 sm:text-sm">关闭</button>
                </div>

                <div class="mt-4 grid gap-4 md:mt-6 md:grid-cols-[220px_1fr] md:gap-5">
                    <div class="overflow-hidden rounded-[1.6rem] bg-parchment/60">
                        <img :src="quickView.currentSku?.cover_image || quickView.data?.cover_image || ''" :alt="quickView.data?.name || ''" class="mall-square-media">
                    </div>
                    <div class="space-y-3.5 sm:space-y-5">
                        <template x-for="(options, name) in quickView.skuOptions" :key="name">
                            <div>
                                <div class="mb-1.5 text-xs font-medium text-ink sm:mb-2 sm:text-sm" x-text="name"></div>
                                <div class="flex flex-wrap gap-1.5 sm:gap-2">
                                    <template x-for="option in options" :key="option">
                                        <button
                                            @click="selectQuickViewOption(name, option)"
                                            :class="quickView.selectedOptions[name] === option ? 'border-bronze bg-bronze text-white' : 'border-bronze/20 bg-parchment/55 text-ink'"
                                            class="rounded-full border px-3 py-1.5 text-xs transition sm:px-4 sm:py-2 sm:text-sm"
                                            x-text="option"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-[1.1rem] border border-bronze/10 bg-parchment/55 p-3 sm:rounded-[1.3rem] sm:p-4">
                                <div class="text-xs text-ink/55 sm:text-sm">当前价格</div>
                                <div class="mt-1.5 text-xl font-semibold text-bronze sm:mt-2 sm:text-2xl">￥<span x-text="formatMoney(quickViewPrice())"></span></div>
                            </div>
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-stretch gap-2">
                                <div class="rounded-[1.1rem] border border-bronze/10 bg-parchment/55 p-3 sm:rounded-[1.3rem] sm:p-4">
                                    <div class="text-xs text-ink/55 sm:text-sm">当前库存</div>
                                    <div class="mt-1.5 text-xl font-semibold text-ink sm:mt-2 sm:text-2xl" x-text="quickViewStock()"></div>
                                </div>
                                <div class="flex items-center gap-1.5 rounded-[1.1rem] border border-bronze/10 bg-white/70 px-2 py-2 sm:gap-2 sm:rounded-[1.3rem] sm:px-3">
                                    <button @click="quickView.quantity = Math.max(1, Number(quickView.quantity) - 1)" type="button" class="h-8 w-8 rounded-full border border-bronze/20 text-sm sm:h-10 sm:w-10">-</button>
                                    <input x-model="quickView.quantity" type="number" min="1" class="w-12 rounded-full border-bronze/20 px-0 text-center text-sm sm:w-16">
                                    <button @click="quickView.quantity = Number(quickView.quantity) + 1" type="button" class="h-8 w-8 rounded-full border border-bronze/20 text-sm sm:h-10 sm:w-10">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2.5 sm:gap-3">
                            <button @click="addQuickViewToCart" type="button" class="rounded-full bg-bronze px-5 py-2.5 text-xs text-white shadow-card transition hover:bg-bronze/90 sm:px-6 sm:py-3 sm:text-sm">确认加入购物车</button>
                            <a :href="quickView.data ? '/mall/products/' + quickView.data.id : '/mall'" class="rounded-full border border-bronze/20 px-5 py-2.5 text-xs text-bronze transition hover:border-bronze hover:bg-bronze/5 sm:px-6 sm:py-3 sm:text-sm">查看详情</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</section>
