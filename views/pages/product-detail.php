<script>window.PAGE_DATA = <?= json_encode_unicode(['product' => $product ?? []]) ?>;</script>
<section x-data="productDetailPage()" class="space-y-4 animate-rise sm:space-y-8">
    <div class="grid gap-3 lg:grid-cols-[1.1fr_0.9fr] sm:gap-6">
        <div class="rounded-[1.4rem] border border-bronze/15 bg-white/80 p-3 shadow-card sm:rounded-[2rem] sm:p-5">
            <div class="grid gap-2.5 sm:grid-cols-[100px_1fr] sm:gap-4">
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
                    class="product-gallery-stage order-1 overflow-hidden rounded-[1.6rem] bg-parchment/70 sm:order-2"
                    @touchstart.passive="startSwipe('gallery', $event)"
                    @touchmove="moveSwipe('gallery', $event)"
                    @touchend.passive="endSwipe('gallery', $event)"
                    @touchcancel="cancelSwipe('gallery')"
                >
                    <button @click="openGalleryViewer()" type="button" class="block h-full w-full cursor-zoom-in">
                        <div class="product-gallery-track" :style="galleryTrackStyle()">
                            <template x-for="(image, index) in galleryImages()" :key="`${image}-${index}`">
                                <div class="product-gallery-slide">
                                    <img :src="image" :alt="product.name || ''" class="product-gallery-image">
                                </div>
                            </template>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-[1.4rem] border border-bronze/15 bg-white/80 p-3 shadow-glow sm:rounded-[2rem] sm:p-6">
            <h1 class="font-display text-3xl text-ink sm:text-4xl" x-text="product.name"></h1>
            <p class="mt-2 text-sm leading-6 text-ink/65 sm:mt-3 sm:leading-7" x-text="product.summary"></p>

            <div class="mt-4 space-y-3 sm:mt-6 sm:space-y-4">
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

            <div class="mt-4 grid grid-cols-2 gap-2.5 sm:mt-6 sm:gap-3">
                <div class="rounded-[1rem] border border-bronze/10 bg-parchment/55 p-3 sm:rounded-[1.4rem] sm:p-4">
                    <div class="text-xs text-ink/55 sm:text-sm">当前价格</div>
                    <div class="mt-1.5 text-xl font-semibold text-bronze sm:mt-2 sm:text-2xl">¥<span x-text="formatMoney(currentPrice)"></span></div>
                </div>
                <div class="rounded-[1rem] border border-bronze/10 bg-parchment/55 p-3 sm:rounded-[1.4rem] sm:p-4">
                    <div class="text-xs text-ink/55 sm:text-sm">当前库存</div>
                    <div class="mt-1.5 truncate text-xl font-semibold text-ink sm:mt-2 sm:text-2xl" x-text="currentStock"></div>
                    <div class="mt-1 truncate text-xs text-ink/55 sm:text-sm" x-text="currentInventoryLabel()"></div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-[minmax(0,1fr)_auto] gap-2.5 sm:mt-4 sm:gap-3">
                <div class="rounded-[1rem] border border-bronze/10 bg-white/75 p-3 text-sm text-ink/70 sm:rounded-[1.4rem] sm:p-4">
                    <div class="flex items-center justify-between">
                        <span>实时总价</span>
                        <span class="font-semibold text-bronze">¥<span x-text="formatMoney(lineTotal())"></span></span>
                    </div>
                    <div x-show="showsMemberPrice()" class="mt-2 flex items-center justify-between text-sage">
                        <span>会员折扣价</span>
                        <span class="font-semibold">¥<span x-text="formatMoney(memberLineTotal())"></span></span>
                    </div>
                </div>

                <div class="flex items-center gap-2 rounded-[1rem] border border-bronze/10 bg-white/70 px-2 py-2 sm:rounded-[1.4rem] sm:px-3">
                    <button @click="quantity = Math.max(1, Number(quantity) - 1)" class="h-8 w-8 rounded-full border border-bronze/20 text-sm sm:h-11 sm:w-11">-</button>
                    <input x-model="quantity" type="number" min="1" class="w-12 rounded-full border-bronze/20 px-0 text-center text-sm sm:w-20">
                    <button @click="quantity = Number(quantity) + 1" class="h-8 w-8 rounded-full border border-bronze/20 text-sm sm:h-11 sm:w-11">+</button>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2 sm:mt-6 sm:gap-3">
                <button @click="buyNow" class="inline-flex min-h-[2.4rem] items-center justify-center rounded-full bg-teal px-2 py-2 text-[11px] text-white shadow-card transition hover:bg-teal/90 sm:min-h-[3rem] sm:px-4 sm:text-sm">立即购买</button>
                <button @click="addToCart" class="inline-flex min-h-[2.4rem] items-center justify-center rounded-full bg-bronze px-2 py-2 text-[11px] text-white shadow-card transition hover:bg-bronze/90 sm:min-h-[3rem] sm:px-4 sm:text-sm">加入购物车</button>
                <button @click="shareProduct" type="button" class="inline-flex min-h-[2.4rem] items-center justify-center rounded-full border border-bronze/20 px-2 py-2 text-[11px] text-bronze transition hover:border-bronze hover:bg-bronze/5 sm:min-h-[3rem] sm:px-4 sm:text-sm" x-text="share.loading ? '准备分享' : '分享商品'"></button>
            </div>
        </div>
    </div>

    <div class="rounded-[1.4rem] border border-bronze/15 bg-white/80 p-3 shadow-card sm:rounded-[2rem] sm:p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm uppercase tracking-[0.3em] text-bronze/70">商品详情</div>
            </div>
        </div>
        <article data-product-detail-body class="product-detail-rich rich-content-body prose prose-stone mt-6 max-w-none" x-html="product.detail_html"></article>
    </div>

    <template x-teleport="body">
        <div x-show="viewer.open" x-cloak x-transition.opacity.duration.180ms class="image-viewer-overlay" @click.self="closeViewer()" @keydown.window.escape="closeViewer()">
            <button @click.stop="closeViewer()" type="button" class="image-viewer-close" aria-label="关闭图片预览">×</button>
            <button x-show="viewer.images.length > 1" @click.stop="viewerPrev()" type="button" class="image-viewer-nav image-viewer-nav-left" aria-label="上一张">‹</button>
            <div class="image-viewer-stage" @touchstart.passive="startSwipe('viewer', $event)" @touchmove="moveSwipe('viewer', $event)" @touchend.passive="endSwipe('viewer', $event)" @touchcancel="cancelSwipe('viewer')">
                <div class="image-viewer-track" :style="viewerTrackStyle()">
                    <template x-for="(image, index) in viewer.images" :key="`${image}-${index}`">
                        <div class="image-viewer-slide">
                            <img :src="image" :alt="product.name || ''" class="image-viewer-image">
                        </div>
                    </template>
                </div>
            </div>
            <button x-show="viewer.images.length > 1" @click.stop="viewerNext()" type="button" class="image-viewer-nav image-viewer-nav-right" aria-label="下一张">›</button>
            <div x-show="viewer.images.length > 1" class="image-viewer-counter">
                <span x-text="viewer.index + 1"></span>
                /
                <span x-text="viewer.images.length"></span>
            </div>
        </div>
    </template>
</section>
