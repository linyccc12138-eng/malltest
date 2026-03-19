<section x-data="orderResultPage()" class="mx-auto max-w-4xl animate-rise">
    <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-6 shadow-glow sm:p-8">
        <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">订单结果</div>
        <h1 class="mt-3 font-display text-4xl text-ink" x-text="resultTitle()"></h1>

        <template x-if="order">
            <div class="mt-6 space-y-6">
                <div class="rounded-[1.5rem] bg-parchment/65 p-5">
                    <div class="grid gap-3 text-sm text-ink/70 sm:grid-cols-2">
                        <div class="flex flex-wrap items-center gap-2">订单号：<span class="font-medium text-ink" x-text="order.order_no"></span><button @click="copyText(order.order_no, '订单号已复制。')" type="button" class="rounded-full border border-bronze/20 px-2.5 py-1 text-xs text-bronze">复制</button></div>
                        <div>下单时间：<span class="font-medium text-ink" x-text="order.placed_at || '--'"></span></div>
                        <div>订单状态：<span class="font-medium text-ink" x-text="statusLabel(order)"></span></div>
                        <div>支付方式：<span class="font-medium text-ink" x-text="paymentMethodLabel(order)"></span></div>
                        <div>收货人：<span class="font-medium text-ink" x-text="order.address_snapshot?.receiver_name || '未填写'"></span></div>
                        <div>联系电话：<span class="font-medium text-ink" x-text="order.address_snapshot?.receiver_phone || '未填写'"></span></div>
                        <div class="sm:col-span-2">收货地址：<span class="font-medium text-ink" x-text="orderReceiverAddress(order) || '未填写地址'"></span></div>
                        <div class="sm:col-span-2">应付金额：<span class="font-medium text-bronze">¥<span x-text="formatMoney(order.payable_amount)"></span></span></div>
                    </div>
                </div>

                <template x-if="orderHasShippingInfo(order)">
                    <div class="rounded-[1.5rem] border border-teal/15 bg-teal/5 p-5">
                        <h2 class="font-display text-2xl text-ink">发货信息</h2>
                        <div class="mt-4 grid gap-3 text-sm text-ink/70 sm:grid-cols-2">
                            <div>发货时间：<span class="font-medium text-ink" x-text="order.shipped_at || '--'"></span></div>
                            <div>物流公司：<span class="font-medium text-ink" x-text="order.shipping_company || '未填写'"></span></div>
                            <div class="sm:col-span-2 flex flex-wrap items-center gap-2">物流单号：<span class="font-medium text-ink" x-text="order.shipping_no || '未填写'"></span><button x-show="order.shipping_no" @click="copyText(order.shipping_no, '物流单号已复制。')" type="button" class="rounded-full border border-teal/20 px-2.5 py-1 text-xs text-teal">复制</button></div>
                        </div>
                    </div>
                </template>

                <template x-if="orderHasCloseInfo(order)">
                    <div class="rounded-[1.5rem] border border-rose/15 bg-rose/5 p-5">
                        <h2 class="font-display text-2xl text-ink">关闭信息</h2>
                        <div class="mt-4 grid gap-3 text-sm text-ink/70 sm:grid-cols-2">
                            <div>关闭时间：<span class="font-medium text-ink" x-text="order.closed_at || '--'"></span></div>
                            <div class="sm:col-span-2">关闭原因：<span class="font-medium text-ink" x-text="orderClosedReasonLabel(order)"></span></div>
                        </div>
                    </div>
                </template>

                <div class="rounded-[1.5rem] border border-bronze/10 bg-white/75 p-5">
                    <h2 class="font-display text-2xl text-ink">商品清单</h2>
                    <div class="mt-4 space-y-3">
                        <template x-for="item in order.items || []" :key="item.id">
                            <div class="flex gap-3 rounded-[1.2rem] border border-bronze/10 bg-parchment/55 p-3">
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-[1rem] bg-parchment/70">
                                    <img :src="item.cover_image || ''" :alt="item.product_name || ''" class="mall-square-media">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-ink" x-text="item.product_name"></div>
                                    <div class="mt-1 text-sm text-ink/55" x-text="item.sku_name || Object.values(item.attributes || {}).join(' / ') || '默认规格'"></div>
                                    <div class="mt-2 text-sm text-ink/60">
                                        数量 <span x-text="item.quantity"></span>
                                        <span class="mx-2">·</span>
                                        小计 ¥<span x-text="formatMoney(item.line_total)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="!order">
            <div class="mt-6 rounded-[1.5rem] bg-parchment/65 p-5 text-sm text-ink/60">正在读取订单结果，请稍候。</div>
        </template>

        <div class="mt-6 flex gap-3">
            <a :href="order ? `/mall/order-detail?order_id=${order.id}` : '/mall/profile?tab=orders'" class="rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">查看订单详情</a>
            <a href="/mall/profile?tab=orders" class="rounded-full border border-bronze/20 px-5 py-3 text-sm text-bronze">查看我的订单</a>
            <a href="/mall" class="rounded-full border border-bronze/20 px-5 py-3 text-sm text-bronze">返回商城</a>
        </div>
    </div>
</section>
