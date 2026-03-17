<section x-data="cartPage()" class="grid gap-6 lg:grid-cols-[1fr_340px] animate-rise">
    <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">购物车</div>
                <h1 class="mt-2 font-display text-3xl text-ink">实时校验库存、会员折扣与失效商品状态</h1>
            </div>
            <a href="/mall" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">继续逛逛</a>
        </div>

        <div class="mt-6 space-y-4">
            <template x-for="item in cart.items" :key="item.id">
                <article class="rounded-[1.4rem] border border-bronze/10 bg-parchment/55 p-4">
                    <div class="flex flex-col gap-4 sm:flex-row">
                        <div class="h-24 w-24 rounded-2xl bg-cover bg-center" :style="`background-image:url(${item.cover_image})`"></div>
                        <div class="flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium text-ink" x-text="item.product_name"></div>
                                    <div class="mt-1 text-sm text-ink/55" x-text="Object.values(item.attributes || {}).join(' / ')"></div>
                                    <div class="mt-2 text-xs" :class="item.item_status === 'valid' ? 'text-sage' : 'text-rose'" x-text="item.item_status === 'valid' ? '可正常购买' : (item.item_status === 'off_shelf' ? '已下架' : '库存不足')"></div>
                                </div>
                                <button @click="remove(item.id)" class="rounded-full border border-rose/20 px-3 py-2 text-xs text-rose">删除</button>
                            </div>
                            <div class="mt-4 flex items-center justify-between gap-4">
                                <div class="text-lg font-semibold text-bronze">¥<span x-text="formatMoney(item.final_price)"></span></div>
                                <div class="flex items-center gap-2">
                                    <button @click="changeQty(item, Math.max(1, item.quantity - 1))" class="h-10 w-10 rounded-full border border-bronze/15">-</button>
                                    <span class="w-10 text-center" x-text="item.quantity"></span>
                                    <button @click="changeQty(item, item.quantity + 1)" class="h-10 w-10 rounded-full border border-bronze/15">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </div>

    <aside class="rounded-[1.8rem] border border-teal/15 bg-white/80 p-5 shadow-glow">
        <div class="text-sm uppercase tracking-[0.35em] text-teal/70">金额汇总</div>
        <div class="mt-4 space-y-3 text-sm text-ink/70">
            <div class="flex justify-between"><span>商品总价</span><span>¥<span x-text="formatMoney(cart.summary.subtotal || 0)"></span></span></div>
            <div class="flex justify-between"><span>优惠金额</span><span>- ¥<span x-text="formatMoney(cart.summary.discount || 0)"></span></span></div>
            <div class="flex justify-between text-lg font-semibold text-bronze"><span>应付金额</span><span>¥<span x-text="formatMoney(cart.summary.payable || 0)"></span></span></div>
        </div>
        <a href="/mall/checkout" class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">去结算</a>
    </aside>
</section>