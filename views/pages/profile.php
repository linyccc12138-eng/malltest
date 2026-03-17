<script>window.PAGE_DATA = <?= json_encode_unicode(['defaultAddress' => $defaultAddress ?? null, 'member' => $currentMember ?? null]) ?>;</script>
<section x-data="profilePage()" class="space-y-6 animate-rise">
    <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-6 shadow-glow">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">用户中心</div>
                <h1 class="mt-3 font-display text-4xl text-ink">管理个人资料、收货地址、订单与会员钱包</h1>
                <p class="mt-3 text-sm leading-7 text-ink/65">这里可以维护昵称、密码和收货地址，查看订单状态、会员等级、余额和商城消费记录，并完成微信绑定。</p>
            </div>
            <div class="rounded-[1.5rem] bg-bronze/8 px-5 py-4 text-sm text-ink/70">
                当前会员等级：<?= $currentMember ? htmlspecialchars($currentMember['fclassesname'], ENT_QUOTES, 'UTF-8') : '未绑定会员' ?>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <template x-for="tab in tabs" :key="tab.key">
            <button
                @click="activeTab = tab.key"
                :class="activeTab === tab.key ? 'bg-bronze text-white' : 'border border-bronze/15 bg-white text-ink'"
                class="rounded-full px-4 py-2 text-sm shadow-card transition"
                x-text="tab.label"
            ></button>
        </template>
    </div>

    <div x-show="activeTab === 'profile'" class="grid gap-6 lg:grid-cols-[1fr_1fr]" x-cloak>
        <form @submit.prevent="saveProfile" class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <h2 class="font-display text-2xl text-ink">个人信息</h2>
            <div class="mt-4 space-y-4">
                <label class="block text-sm text-ink/70">
                    昵称
                    <input x-model="profile.nickname" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                </label>
                <label class="block text-sm text-ink/70">
                    手机号
                    <input x-model="profile.phone" type="text" inputmode="numeric" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                </label>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">保存资料</button>
                    <a href="/mall/bind/wechat" class="rounded-full border border-bronze/15 px-5 py-3 text-sm text-bronze">绑定微信</a>
                </div>
            </div>
        </form>

        <form @submit.prevent="changePassword" class="rounded-[1.8rem] border border-sage/15 bg-white/80 p-5 shadow-card">
            <h2 class="font-display text-2xl text-ink">修改密码</h2>
            <div class="mt-4 space-y-4">
                <label class="block text-sm text-ink/70">
                    当前密码
                    <input x-model="password.current_password" type="password" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                </label>
                <label class="block text-sm text-ink/70">
                    新密码
                    <input x-model="password.new_password" type="password" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                </label>
                <button type="submit" class="rounded-full bg-sage px-5 py-3 text-sm text-white shadow-card">更新密码</button>
            </div>
        </form>
    </div>

    <div x-show="activeTab === 'addresses'" class="space-y-6" x-cloak>
        <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl text-ink">收货地址</h2>
                    <p class="mt-2 text-sm text-ink/60">新增和编辑地址会以模态框方式打开，并自动避开顶部导航栏。</p>
                </div>
                <button @click="openAddressModal()" type="button" class="rounded-full bg-teal px-4 py-2 text-sm text-white shadow-card">新增地址</button>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <template x-for="item in addresses" :key="item.id">
                    <article class="rounded-[1.4rem] border border-bronze/10 bg-parchment/55 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-ink">
                                    <span x-text="item.receiver_name"></span>
                                    <span class="ml-2 text-sm text-ink/55" x-text="item.receiver_phone"></span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-ink/65">
                                    <span x-text="item.province"></span>
                                    <span x-text="item.city"></span>
                                    <span x-text="item.district"></span>
                                    <span x-text="item.detail_address"></span>
                                </p>
                            </div>
                            <span x-show="item.is_default == 1" class="rounded-full bg-bronze/10 px-3 py-1 text-xs text-bronze">默认</span>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button @click="editAddress(item)" type="button" class="rounded-full border border-bronze/20 px-3 py-2 text-xs text-bronze">编辑</button>
                            <button @click="removeAddress(item.id)" type="button" class="rounded-full border border-rose/20 px-3 py-2 text-xs text-rose">删除</button>
                        </div>
                    </article>
                </template>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'orders'" class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <template x-for="group in orderGroups" :key="group.key">
                    <button
                        @click="switchOrderGroup(group.key)"
                        :class="orderGroup === group.key ? 'bg-bronze text-white' : 'border border-bronze/15 bg-parchment/55 text-ink'"
                        class="rounded-full px-4 py-2 text-sm"
                        x-text="group.label"
                    ></button>
                </template>
            </div>
            <label class="flex items-center gap-2 text-sm text-ink/60">
                每页
                <select x-model="orderPager.page_size" @change="changeOrderPageSize(orderPager.page_size)" class="rounded-full border-bronze/15 bg-parchment/55 pr-8">
                    <template x-for="size in orderPageSizeOptions" :key="`profile-order-size-${size}`">
                        <option :value="size" x-text="`${size} 条`"></option>
                    </template>
                </select>
            </label>
        </div>

        <div class="mt-5 space-y-4">
            <template x-for="order in orders" :key="order.id">
                <article class="rounded-[1.5rem] border border-bronze/10 bg-parchment/55 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-sm text-ink/55">订单号 <span x-text="order.order_no"></span></div>
                            <div class="mt-1 font-medium text-ink">状态：<span x-text="order.status"></span></div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-ink/55">应付金额</div>
                            <div class="text-xl font-semibold text-bronze">¥<span x-text="formatMoney(order.payable_amount)"></span></div>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button @click="openOrder(order.id)" type="button" class="rounded-full border border-bronze/15 px-3 py-2 text-xs text-bronze">查看详情</button>
                        <button x-show="['pending_payment','pending_shipment'].includes(order.status)" @click="cancelOrder(order.id)" type="button" class="rounded-full border border-rose/20 px-3 py-2 text-xs text-rose">取消订单</button>
                        <button x-show="order.status === 'pending_receipt'" @click="completeOrder(order.id)" type="button" class="rounded-full border border-sage/20 px-3 py-2 text-xs text-sage">确认收货</button>
                    </div>
                </article>
            </template>
        </div>

        <div class="mt-6 flex flex-col gap-3 border-t border-bronze/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-ink/55">共 <span x-text="orderPager.total"></span> 条订单</div>
            <div class="flex flex-wrap items-center gap-2">
                <button @click="changeOrderPage(orderPager.page - 1)" :disabled="orderPager.page <= 1" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">上一页</button>
                <template x-for="page in orderPages()" :key="`profile-order-page-${page}`">
                    <button
                        @click="changeOrderPage(page)"
                        :class="page === orderPager.page ? 'bg-bronze text-white' : 'border border-bronze/15 bg-white text-ink'"
                        class="min-w-10 rounded-full px-3 py-2 text-sm"
                        x-text="page"
                    ></button>
                </template>
                <button @click="changeOrderPage(orderPager.page + 1)" :disabled="orderPager.page >= orderPager.total_pages" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">下一页</button>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'wallet'" class="grid gap-6 lg:grid-cols-[1fr_1fr]" x-cloak>
        <div class="rounded-[1.8rem] border border-bronze/15 bg-white/80 p-5 shadow-card">
            <h2 class="font-display text-2xl text-ink">我的钱包</h2>
            <div class="mt-4 space-y-3 text-sm text-ink/70">
                <div>会员等级：<span class="font-medium text-ink" x-text="wallet.member?.fclassesname || '未绑定会员'"></span></div>
                <div>会员余额：<span class="font-medium text-bronze">¥<span x-text="formatMoney(wallet.member?.fbalance || 0)"></span></span></div>
                <div>会员折扣：<span class="font-medium text-ink" x-text="wallet.member?.foff || '1.00'"></span></div>
            </div>
        </div>
        <div class="rounded-[1.8rem] border border-teal/15 bg-white/80 p-5 shadow-card">
            <h2 class="font-display text-2xl text-ink">商城消费记录</h2>
            <div class="mt-4 space-y-3 text-sm text-ink/70">
                <template x-for="record in wallet.records" :key="record.id">
                    <div class="rounded-[1.2rem] border border-teal/10 bg-teal/5 p-3">
                        <div class="font-medium text-ink" x-text="record.remark"></div>
                        <div class="mt-1 text-xs text-ink/55">订单 <span x-text="record.order_id"></span> / 金额 ¥<span x-text="formatMoney(record.amount)"></span></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div
            x-show="addressModalOpen"
            x-cloak
            x-transition.opacity.duration.180ms
            class="modal-overlay"
            @click.self="closeAddressModal()"
        >
            <div x-transition.scale.opacity.duration.220ms class="modal-panel w-full max-w-2xl overflow-y-auto rounded-[1.9rem] border border-bronze/10 bg-white/95 p-6 shadow-glow">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-2xl text-ink" x-text="addressForm.id ? '编辑地址' : '新增地址'"></h3>
                        <p class="mt-1 text-sm text-ink/55">支持省、市、区三级联动，默认地址会自动置顶显示。</p>
                    </div>
                    <button @click="closeAddressModal()" type="button" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze">关闭</button>
                </div>

                <form @submit.prevent="saveAddress" class="mt-5 space-y-4">
                    <input type="hidden" x-model="addressForm.id">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm text-ink/70">
                            收货人
                            <input x-model="addressForm.receiver_name" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                        </label>
                        <label class="block text-sm text-ink/70">
                            手机号
                            <input x-model="addressForm.receiver_phone" type="text" inputmode="numeric" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <select x-model="addressForm.province" @change="syncCities" class="rounded-2xl border-bronze/15 bg-parchment/55" required>
                            <option value="">省份</option>
                            <template x-for="province in regions" :key="province.name">
                                <option :value="province.name" x-text="province.name"></option>
                            </template>
                        </select>
                        <select x-model="addressForm.city" @change="syncDistricts" class="rounded-2xl border-bronze/15 bg-parchment/55" required>
                            <option value="">城市</option>
                            <template x-for="city in cities" :key="city.name">
                                <option :value="city.name" x-text="city.name"></option>
                            </template>
                        </select>
                        <select x-model="addressForm.district" class="rounded-2xl border-bronze/15 bg-parchment/55" required>
                            <option value="">区县</option>
                            <template x-for="district in districts" :key="district">
                                <option :value="district" x-text="district"></option>
                            </template>
                        </select>
                    </div>

                    <label class="block text-sm text-ink/70">
                        详细地址
                        <textarea x-model="addressForm.detail_address" class="mt-2 min-h-28 w-full rounded-2xl border-bronze/15 bg-parchment/55" required></textarea>
                    </label>

                    <label class="flex items-center gap-2 text-sm text-ink/70">
                        <input x-model="addressForm.is_default" type="checkbox" class="rounded border-bronze/20">
                        设为默认地址
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button @click="closeAddressModal()" type="button" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">取消</button>
                        <button type="submit" class="rounded-full bg-teal px-5 py-2.5 text-sm text-white shadow-card">保存地址</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</section>
