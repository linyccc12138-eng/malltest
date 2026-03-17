<script>window.PAGE_DATA = <?= json_encode_unicode(['product' => $product ?? []]) ?>;</script>
<section x-data="productDetailPage()" class="space-y-8 animate-rise">
    <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <div class="grid gap-4 sm:grid-cols-[100px_1fr]">
                <div class="order-2 flex gap-3 overflow-x-auto pb-2 sm:order-1 sm:flex-col">
                    <template x-for="(image, index) in galleryImages()" :key="`${image}-${index}`">
                        <button
                            @click="selectGalleryImage(image)"
                            :class="activeImage === image ? 'border-bronze shadow-card' : 'border-bronze/15'"
                            class="overflow-hidden rounded-2xl border bg-parchment/60 transition"
                        >
                            <img :src="image" :alt="product.name || ''" class="h-20 min-w-20 object-cover">
                        </button>
                    </template>
                </div>
                <div
                    class="order-1 overflow-hidden rounded-[1.6rem] bg-parchment/70 sm:order-2"
                    @touchstart.passive="startSwipe('gallery', $event)"
                    @touchend.passive="endSwipe('gallery', $event)"
                >
                    <button @click="openGalleryViewer()" type="button" class="block w-full cursor-zoom-in">
                        <img :src="activeImage" :alt="product.name || ''" class="block aspect-[4/5] w-full object-cover transition duration-500">
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-6 shadow-glow">
            <div class="text-sm uppercase tracking-[0.35em] text-bronze/70" x-text="product.brand || '精选品牌'"></div>
            <h1 class="mt-3 font-display text-4xl text-ink" x-text="product.name"></h1>
            <p class="mt-3 text-sm leading-7 text-ink/65" x-text="product.summary"></p>

            <div class="mt-5 flex items-end gap-3">
                <div class="text-3xl font-semibold text-bronze">¥<span x-text="formatMoney(currentPrice)"></span></div>
                <div class="pb-1 text-sm text-ink/45 line-through">¥<span x-text="formatMoney(product.market_price)"></span></div>
            </div>

            <div class="mt-4 text-sm text-ink/60">
                评分 <span x-text="product.rating"></span>
                · 库存 <span x-text="currentStock"></span>
                · <?= !empty($product['support_member_discount']) ? '支持会员折扣' : '不参与会员折扣' ?>
            </div>

            <div class="mt-6 space-y-4">
                <template x-for="(options, name) in skuOptions" :key="name">
                    <div>
                        <div class="mb-2 text-sm font-medium text-ink" x-text="name"></div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="option in options" :key="option">
                                <button @click="selectOption(name, option)" :class="selectedOptions[name] === option ? 'border-bronze bg-bronze text-white' : 'border-bronze/20 bg-parchment/55 text-ink'" class="rounded-full border px-4 py-2 text-sm transition" x-text="option"></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button @click="quantity = Math.max(1, Number(quantity) - 1)" class="h-11 w-11 rounded-full border border-bronze/20">-</button>
                <input x-model="quantity" type="number" min="1" class="w-24 rounded-full border-bronze/20 text-center">
                <button @click="quantity = Number(quantity) + 1" class="h-11 w-11 rounded-full border border-bronze/20">+</button>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button @click="addToCart" class="rounded-full bg-bronze px-6 py-3 text-sm text-white shadow-card transition hover:bg-bronze/90">加入购物车</button>
                <a href="/mall/cart" class="rounded-full border border-bronze/20 px-6 py-3 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">查看购物车</a>
            </div>
        </div>
    </div>

    <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-6 shadow-card">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm uppercase tracking-[0.3em] text-bronze/70">商品详情</div>
            </div>
        </div>
        <article data-product-detail-body class="product-detail-rich prose prose-stone mt-6 max-w-none" x-html="product.detail_html"></article>
    </div>

    <template x-teleport="body">
        <div x-show="viewer.open" x-cloak x-transition.opacity.duration.180ms class="image-viewer-overlay" @click.self="closeViewer()" @keydown.window.escape="closeViewer()">
            <button @click="closeViewer()" type="button" class="image-viewer-close" aria-label="关闭图片预览">×</button>
            <button x-show="viewer.images.length > 1" @click="viewerPrev()" type="button" class="image-viewer-nav image-viewer-nav-left" aria-label="上一张">‹</button>
            <div class="image-viewer-stage" @touchstart.passive="startSwipe('viewer', $event)" @touchend.passive="endSwipe('viewer', $event)">
                <img :src="viewerCurrentImage()" :alt="product.name || ''" class="image-viewer-image">
            </div>
            <button x-show="viewer.images.length > 1" @click="viewerNext()" type="button" class="image-viewer-nav image-viewer-nav-right" aria-label="下一张">›</button>
            <div x-show="viewer.images.length > 1" class="image-viewer-counter">
                <span x-text="viewer.index + 1"></span>
                /
                <span x-text="viewer.images.length"></span>
            </div>
        </div>
    </template>
</section>
