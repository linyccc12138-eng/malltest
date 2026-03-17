<script>window.PAGE_DATA = <?= json_encode_unicode(['cart' => $cart ?? [], 'addresses' => $addresses ?? []]) ?>;</script>
<section x-data="checkoutPage()" class="grid gap-6 lg:grid-cols-[1fr_360px] animate-rise">
    <div class="space-y-6">
        <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">确认订单</div>
            <h1 class="mt-2 font-display text-3xl text-ink">购物车 → 确认页 → 支付 → 结果页</h1>
            <p class="mt-3 text-sm leading-7 text-ink/65">提交订单后立即扣减库存，未支付订单将在 15 分钟后自动关闭并回滚库存。</p>
        </div>

        <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
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

        <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <h2 class="font-display text-2xl text-ink">商品清单</h2>
            <div class="mt-4 space-y-3">
                <template x-for="item in cart.items" :key="item.id">
                    <div class="flex items-center justify-between rounded-[1.3rem] border border-bronze/10 bg-parchment/55 p-4">
                        <div>
                            <div class="font-medium text-ink" x-text="item.product_name"></div>
                            <div class="mt-1 text-sm text-ink/55">数量 <span x-text="item.quantity"></span></div>
                        </div>
                        <div class="text-bronze">¥<span x-text="formatMoney(item.final_price * item.quantity)"></span></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <aside class="rounded-[1.8rem] border border-teal/15 bg-white/80 p-5 shadow-glow">
        <div class="text-sm uppercase tracking-[0.35em] text-teal/70">支付信息</div>
        <div class="mt-4 space-y-3 text-sm text-ink/70">
            <div class="flex justify-between"><span>商品总价</span><span>¥<span x-text="formatMoney(cart.summary.subtotal || 0)"></span></span></div>
            <div class="flex justify-between"><span>会员优惠</span><span>- ¥<span x-text="formatMoney(cart.summary.discount || 0)"></span></span></div>
            <div class="flex justify-between text-lg font-semibold text-bronze"><span>应付金额</span><span>¥<span x-text="formatMoney(cart.summary.payable || 0)"></span></span></div>
        </div>
        <div class="mt-6 space-y-3">
            <button @click="createOrder('balance')" class="w-full rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">余额支付</button>
            <button @click="createOrder('wechat')" class="w-full rounded-full bg-teal px-5 py-3 text-sm text-white shadow-card">微信支付</button>
        </div>
    </aside>
</section>