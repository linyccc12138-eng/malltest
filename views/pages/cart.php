<section x-data="cartPage()" class="grid gap-6 lg:grid-cols-[1fr_340px] animate-rise">
    <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">购物车</div>
                <h1 class="mt-2 font-display text-3xl text-ink">勾选需要结算的商品，数量可直接填写调整</h1>
            </div>
            <a href="/mall" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">继续逛逛</a>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 border-t border-bronze/10 pt-4 text-sm text-ink/60">
            <label class="inline-flex items-center gap-2">
                <input :checked="allSelected()" @change="toggleAll($event.target.checked)" type="checkbox" class="rounded border-bronze/20">
                <span>全选</span>
            </label>
            <div>已选 <span x-text="selectedValidIds().length"></span> 件可结算商品</div>
        </div>

        <div class="mt-6 space-y-4">
            <template x-for="item in cart.items" :key="item.id">
                <article class="rounded-[1.4rem] border border-bronze/10 bg-parchment/55 p-4">
                    <div class="flex gap-3">
                        <label class="flex pt-1">
                            <input :checked="selectedIds.includes(item.id)" @change="toggleSelected(item.id, $event.target.checked)" type="checkbox" class="rounded border-bronze/20">
                        </label>
                        <div class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-parchment/70">
                            <img :src="item.cover_image || ''" :alt="item.product_name || ''" class="mall-square-media">
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a :href="'/mall/products/' + item.product_id" class="block truncate font-medium text-ink transition hover:text-bronze" x-text="item.product_name"></a>
                                    <div class="mt-1 text-sm text-ink/55" x-text="Object.values(item.attributes || {}).join(' / ') || '默认规格'"></div>
                                    <div class="mt-2 text-xs" :class="item.item_status === 'valid' ? 'text-sage' : 'text-rose'" x-text="item.item_status === 'valid' ? '可正常购买' : (item.item_status === 'off_shelf' ? '已下架' : '库存不足')"></div>
                                </div>
                                <button @click="remove(item.id)" class="rounded-full border border-rose/20 px-3 py-2 text-xs text-rose">删除</button>
                            </div>
                            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <div class="text-lg font-semibold text-bronze">¥<span x-text="formatMoney(item.final_price)"></span></div>
                                    <div class="mt-1 text-xs text-ink/45">库存 <span x-text="item.sku_stock"></span></div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="changeQty(item, Math.max(1, Number(item.quantity) - 1))" class="h-10 w-10 rounded-full border border-bronze/15">-</button>
                                    <input :value="item.quantity" @change="changeQty(item, Math.max(1, Number($event.target.value || item.quantity)))" type="number" min="1" class="w-16 rounded-full border-bronze/15 px-0 text-center">
                                    <button @click="changeQty(item, Number(item.quantity) + 1)" class="h-10 w-10 rounded-full border border-bronze/15">+</button>
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
            <div class="flex justify-between"><span>商品总价</span><span>¥<span x-text="formatMoney(selectedSummary().subtotal)"></span></span></div>
            <div class="flex justify-between"><span>优惠金额</span><span>- ¥<span x-text="formatMoney(selectedSummary().discount)"></span></span></div>
            <div class="flex justify-between text-lg font-semibold text-bronze"><span>应付金额</span><span>¥<span x-text="formatMoney(selectedSummary().payable)"></span></span></div>
        </div>
        <button @click="checkoutSelected" type="button" class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">去结算</button>
    </aside>
</section>
