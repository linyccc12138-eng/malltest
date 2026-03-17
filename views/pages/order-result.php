<section x-data="orderResultPage()" class="mx-auto max-w-3xl animate-rise">
    <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-6 shadow-glow sm:p-8">
        <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">订单结果</div>
        <h1 class="mt-3 font-display text-4xl text-ink">支付完成后可在这里查看订单状态</h1>
        <div class="mt-6 rounded-[1.5rem] bg-parchment/65 p-5">
            <template x-if="order">
                <div class="space-y-3 text-sm text-ink/70">
                    <div>订单号：<span class="font-medium text-ink" x-text="order.order_no"></span></div>
                    <div>订单状态：<span class="font-medium text-ink" x-text="order.status"></span></div>
                    <div>支付方式：<span class="font-medium text-ink" x-text="order.payment_method"></span></div>
                    <div>应付金额：<span class="font-medium text-bronze">¥<span x-text="formatMoney(order.payable_amount)"></span></span></div>
                </div>
            </template>
            <template x-if="!order">
                <div class="text-sm text-ink/60">正在读取订单结果，请稍候。</div>
            </template>
        </div>
        <div class="mt-6 flex gap-3">
            <a href="/mall/profile" class="rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">查看我的订单</a>
            <a href="/mall" class="rounded-full border border-bronze/20 px-5 py-3 text-sm text-bronze">返回商城</a>
        </div>
    </div>
</section>