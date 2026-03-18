<script>window.PAGE_DATA = <?= json_encode_unicode(['checkout' => $checkout ?? [], 'addresses' => $addresses ?? []]) ?>;</script>
<section x-data="checkoutPage()" class="grid gap-6 lg:grid-cols-[1fr_360px] animate-rise">
    <div class="space-y-6">
        <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">确认订单</div>
            <h1 class="mt-2 font-display text-3xl text-ink" x-text="checkoutTitle()"></h1>
            <p class="mt-3 text-sm leading-7 text-ink/65">提交订单后立即扣减库存，未支付订单将在 15 分钟后自动关闭并回滚库存。</p>
        </div>

        <div x-show="checkout.mode !== 'repay'" class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card" x-cloak>
            <h2 class="font-display text-2xl text-ink">选择收货地址</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <template x-for="item in addresses" :key="item.id">
                    <button @click="selectedAddressId = item.id" :class="selectedAddressId === item.id ? 'border-bronze bg-bronze/5' : 'border-bronze/10 bg-parchment/55'" class="rounded-[1.4rem] border p-4 text-left transition">
                        <div class="font-medium text-ink"><span x-text="item.receiver_name"></span> <span class="text-sm text-ink/55" x-text="item.receiver_phone"></span></div>
                        <div class="mt-2 text-sm leading-6 text-ink/65"><span x-text="item.province"></span><span x-text="item.city"></span><span x-text="item.district"></span><span x-text="item.detail_address"></span></div>
                    </button>
                </template>
            </div>
        </div>

        <div x-show="checkout.mode === 'repay'" class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card" x-cloak>
            <h2 class="font-display text-2xl text-ink">订单收货地址</h2>
            <div class="mt-4 rounded-[1.4rem] border border-bronze/10 bg-parchment/55 p-4 text-sm leading-7 text-ink/70">
                <div class="font-medium text-ink">
                    <span x-text="checkout.address_snapshot?.receiver_name || ''"></span>
                    <span class="ml-2 text-ink/55" x-text="checkout.address_snapshot?.receiver_phone || ''"></span>
                </div>
                <div class="mt-2">
                    <span x-text="checkout.address_snapshot?.province || ''"></span>
                    <span x-text="checkout.address_snapshot?.city || ''"></span>
                    <span x-text="checkout.address_snapshot?.district || ''"></span>
                    <span x-text="checkout.address_snapshot?.detail_address || ''"></span>
                </div>
            </div>
        </div>

        <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <h2 class="font-display text-2xl text-ink">商品清单</h2>
            <div class="mt-4 space-y-3">
                <template x-for="item in checkout.items" :key="`${checkout.mode}-${item.id}-${item.sku_id}`">
                    <div class="rounded-[1.3rem] border border-bronze/10 bg-parchment/55 p-4">
                        <div class="flex gap-3">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-[1rem] bg-parchment/70">
                                <img :src="item.cover_image || ''" :alt="item.product_name || ''" class="mall-square-media">
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-ink" x-text="item.product_name"></div>
                                <div class="mt-1 text-sm text-ink/55" x-text="checkoutItemSpec(item)"></div>
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <div class="text-bronze">¥<span x-text="formatMoney(item.final_price)"></span></div>
                                    <div x-show="checkout.mode === 'buy_now'" class="flex items-center gap-2">
                                        <button @click="updateBuyNowQuantity(Math.max(1, buyNowQuantity() - 1))" type="button" class="h-9 w-9 rounded-full border border-bronze/15">-</button>
                                        <input :value="buyNowQuantity()" @change="updateBuyNowQuantity(Math.max(1, Number($event.target.value || buyNowQuantity())))" type="number" min="1" class="w-16 rounded-full border-bronze/15 px-0 text-center">
                                        <button @click="updateBuyNowQuantity(buyNowQuantity() + 1)" type="button" class="h-9 w-9 rounded-full border border-bronze/15">+</button>
                                    </div>
                                    <div x-show="checkout.mode !== 'buy_now'" class="text-sm text-ink/60">数量 <span x-text="item.quantity"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <aside class="rounded-[1.8rem] border border-teal/15 bg-white/80 p-5 shadow-glow">
        <div class="text-sm uppercase tracking-[0.35em] text-teal/70">支付信息</div>
        <div class="mt-4 space-y-3 text-sm text-ink/70">
            <div class="flex justify-between"><span>商品总价</span><span>¥<span x-text="formatMoney(displaySummary().subtotal)"></span></span></div>
            <div class="flex justify-between"><span>会员优惠</span><span>- ¥<span x-text="formatMoney(displaySummary().discount)"></span></span></div>
            <div class="flex justify-between text-lg font-semibold text-bronze"><span>应付金额</span><span>¥<span x-text="formatMoney(displaySummary().payable)"></span></span></div>
        </div>
        <div class="mt-6 space-y-3">
            <button @click="createOrder('balance')" class="w-full rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">余额支付</button>
            <button @click="createOrder('wechat')" class="w-full rounded-full bg-teal px-5 py-3 text-sm text-white shadow-card">微信支付</button>
        </div>
    </aside>
</section>
