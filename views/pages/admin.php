<script>window.PAGE_DATA = <?= json_encode_unicode(['settings' => $settingsData ?? []]) ?>;</script>
<section x-data="adminPage()" class="space-y-6 animate-rise">
    <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-4 shadow-glow">
        <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">Admin Console</div>
        <h1 class="mt-2 font-display text-3xl text-ink sm:text-[2rem]">会员联动电商管理后台</h1>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-ink/65">统一管理商品、分类、订单、用户、会员、活动、微信能力和系统日志。商品与活动使用独立编辑页，其余基础资料使用模态框维护。</p>
    </div>

    <div class="flex flex-wrap gap-2">
        <template x-for="tab in tabs" :key="tab.key">
            <button
                @click="changeTab(tab.key)"
                :class="activeTab === tab.key ? 'bg-bronze text-white' : 'border border-bronze/15 bg-white text-ink'"
                class="rounded-full px-4 py-2 text-sm shadow-card transition"
                x-text="tab.label"
            ></button>
        </template>
    </div>

    <div x-show="activeTab === 'dashboard'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" x-cloak>
        <template x-for="card in dashboardCards" :key="card.key">
            <div class="rounded-[1.6rem] border border-bronze/10 bg-white/80 p-5 shadow-card">
                <div class="text-sm text-ink/55" x-text="card.label"></div>
                <div class="mt-3 font-display text-4xl text-ink" x-text="card.value"></div>
            </div>
        </template>
        <div class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card sm:col-span-2 xl:col-span-4">
            <canvas id="salesChart" height="110"></canvas>
        </div>
    </div>

    <div x-show="activeTab === 'products'" class="space-y-6" x-cloak>
        <div class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl text-ink">商品列表</h2>
                    <p class="mt-2 text-sm text-ink/60">商品编辑已拆分为独立页面，新建或编辑时会跳转到商品编辑页。商品列表已改为分页加载。</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button @click="goToProductEditor()" class="rounded-full bg-bronze px-4 py-2 text-sm text-white">新建商品</button>
                    <button @click="batchProductAction('shelf_on')" class="rounded-full border border-sage/20 px-4 py-2 text-sm text-sage">批量上架</button>
                    <button @click="batchProductAction('shelf_off')" class="rounded-full border border-rose/20 px-4 py-2 text-sm text-rose">批量下架</button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-bronze/10 pt-4">
                <div class="text-sm text-ink/55">共 <span x-text="productPager.total"></span> 个商品</div>
                <label class="flex items-center gap-2 text-sm text-ink/60">
                    每页
                    <select x-model="productPager.page_size" @change="setPagerSize('productPager', productPager.page_size, 'loadProducts')" class="rounded-full border-bronze/15 bg-parchment/55 pr-8">
                        <template x-for="size in pageSizeOptions" :key="`product-size-${size}`">
                            <option :value="size" x-text="`${size} 条`"></option>
                        </template>
                    </select>
                </label>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <template x-for="item in products" :key="item.id">
                    <article class="rounded-[1.5rem] border border-bronze/10 bg-parchment/55 p-4">
                        <div class="flex items-start gap-4">
                            <label class="flex pt-1">
                                <input type="checkbox" x-model="selectedProductIds" :value="item.id" class="rounded border-bronze/20">
                            </label>
                            <div class="h-20 w-20 overflow-hidden rounded-[1.2rem] border border-bronze/10 bg-white">
                                <template x-if="item.cover_image">
                                    <img :src="item.cover_image" alt="商品图片" class="h-full w-full object-cover">
                                </template>
                                <template x-if="!item.cover_image">
                                    <div class="flex h-full w-full items-center justify-center text-xs text-ink/40">暂无图片</div>
                                </template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium text-ink" x-text="item.name"></div>
                                <div class="mt-1 text-sm text-ink/55">
                                    <span x-text="item.category_name || '未分类'"></span>
                                    <span class="mx-1">/</span>
                                    <span x-text="item.brand || '未设置品牌'"></span>
                                </div>
                                <div class="mt-1 text-sm text-ink/55">
                                    价格 ¥<span x-text="formatMoney(item.price)"></span>
                                    <span class="mx-1">/</span>
                                    库存 <span x-text="item.stock_total"></span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button @click="goToProductEditor(item.id)" class="rounded-full border border-bronze/15 px-3 py-2 text-xs text-bronze">编辑商品</button>
                                </div>
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-bronze/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-ink/55">第 <span x-text="productPager.page"></span> / <span x-text="productPager.total_pages"></span> 页</div>
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="setPagerPage('productPager', productPager.page - 1, 'loadProducts')" :disabled="productPager.page <= 1" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">上一页</button>
                    <template x-for="page in pageNumbers(productPager)" :key="`product-page-${page}`">
                        <button
                            @click="setPagerPage('productPager', page, 'loadProducts')"
                            :class="page === productPager.page ? 'bg-bronze text-white' : 'border border-bronze/15 bg-white text-ink'"
                            class="min-w-10 rounded-full px-3 py-2 text-sm"
                            x-text="page"
                        ></button>
                    </template>
                    <button @click="setPagerPage('productPager', productPager.page + 1, 'loadProducts')" :disabled="productPager.page >= productPager.total_pages" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">下一页</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'categories'" class="space-y-6" x-cloak>
        <div class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl text-ink">分类管理</h2>
                    <p class="mt-2 text-sm text-ink/60">默认仅显示分类树，新建或编辑时再弹出分类模态框。</p>
                </div>
                <button @click="openCategoryModal()" class="rounded-full bg-sage px-4 py-2 text-sm text-white">新建分类</button>
            </div>

            <div class="mt-5 space-y-3">
                <template x-for="item in categories" :key="item.id">
                    <div
                        class="rounded-[1.4rem] border border-bronze/10 bg-parchment/55 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <button @click="toggleCategoryExpand(item)" type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left">
                                <span
                                    :class="item.has_children ? 'opacity-100' : 'opacity-0'"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-bronze/15 bg-white/80 text-xs text-bronze transition"
                                    x-text="item.expanded ? '−' : '+'"
                                ></span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2" :style="`padding-left:${item.depth * 16}px`">
                                        <span class="font-medium text-ink" x-text="item.name"></span>
                                        <span x-show="item.has_children" class="rounded-full bg-bronze/10 px-2 py-0.5 text-[11px] text-bronze">上级分类</span>
                                    </div>
                                    <div class="mt-1 text-xs text-ink/55">
                                        ID <span x-text="item.id"></span>
                                        <span class="mx-1">/</span>
                                        层级 <span x-text="item.level"></span>
                                        <span class="mx-1">/</span>
                                        父级 <span x-text="parentCategoryName(item.parent_id)"></span>
                                    </div>
                                </div>
                            </button>
                            <div class="min-w-0 text-right">
                                <div class="mt-1 text-xs text-ink/55">
                                    <span x-text="item.has_children ? '点击左侧展开/收起子分类' : '末级分类'"></span>
                                </div>
                            </div>
                            <button @click="openCategoryModal(item)" type="button" class="rounded-full border border-bronze/15 px-3 py-2 text-xs text-bronze">编辑</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'orders'" class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <template x-for="group in orderGroups" :key="group.key">
                    <button
                        @click="switchAdminOrderGroup(group.key)"
                        :class="adminOrderGroup === group.key ? 'bg-bronze text-white' : 'border border-bronze/15 bg-parchment/55 text-ink'"
                        class="rounded-full px-4 py-2 text-sm"
                        x-text="group.label"
                    ></button>
                </template>
            </div>
            <label class="flex items-center gap-2 text-sm text-ink/60">
                每页
                <select x-model="orderPager.page_size" @change="setPagerSize('orderPager', orderPager.page_size, 'loadAdminOrders')" class="rounded-full border-bronze/15 bg-parchment/55 pr-8">
                    <template x-for="size in pageSizeOptions" :key="`admin-order-size-${size}`">
                        <option :value="size" x-text="`${size} 条`"></option>
                    </template>
                </select>
            </label>
        </div>

        <div class="mt-5 space-y-4">
            <template x-for="order in adminOrders" :key="order.id">
                <article class="rounded-[1.4rem] border border-bronze/10 bg-parchment/55 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-medium text-ink" x-text="order.order_no"></div>
                            <div class="mt-1 text-sm text-ink/55">
                                用户 ID <span x-text="order.user_id"></span>
                                <span class="mx-1">/</span>
                                状态 <span x-text="order.status"></span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button @click="ship(order)" class="rounded-full border border-teal/15 px-3 py-2 text-xs text-teal">发货</button>
                            <button @click="closeAdminOrder(order.id)" class="rounded-full border border-rose/20 px-3 py-2 text-xs text-rose">关闭</button>
                        </div>
                    </div>
                </article>
            </template>
        </div>

        <div class="mt-6 flex flex-col gap-3 border-t border-bronze/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-ink/55">共 <span x-text="orderPager.total"></span> 条订单</div>
            <div class="flex flex-wrap items-center gap-2">
                <button @click="setPagerPage('orderPager', orderPager.page - 1, 'loadAdminOrders')" :disabled="orderPager.page <= 1" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">上一页</button>
                <template x-for="page in pageNumbers(orderPager)" :key="`admin-order-page-${page}`">
                    <button
                        @click="setPagerPage('orderPager', page, 'loadAdminOrders')"
                        :class="page === orderPager.page ? 'bg-bronze text-white' : 'border border-bronze/15 bg-white text-ink'"
                        class="min-w-10 rounded-full px-3 py-2 text-sm"
                        x-text="page"
                    ></button>
                </template>
                <button @click="setPagerPage('orderPager', orderPager.page + 1, 'loadAdminOrders')" :disabled="orderPager.page >= orderPager.total_pages" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">下一页</button>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'users'" class="space-y-6" x-cloak>
        <div class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl text-ink">用户管理</h2>
                    <p class="mt-2 text-sm text-ink/60">新增用户初始密码默认使用手机号后 8 位，可直接禁用/启用账号，也支持一键重置默认密码。</p>
                </div>
                <button @click="openUserModal()" class="rounded-full bg-sage px-4 py-2 text-sm text-white">新增用户</button>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-bronze/10 pt-4">
                <div class="text-sm text-ink/55">共 <span x-text="userPager.total"></span> 个用户</div>
                <label class="flex items-center gap-2 text-sm text-ink/60">
                    每页
                    <select x-model="userPager.page_size" @change="setPagerSize('userPager', userPager.page_size, 'loadUsers')" class="rounded-full border-bronze/15 bg-parchment/55 pr-8">
                        <template x-for="size in pageSizeOptions" :key="`user-size-${size}`">
                            <option :value="size" x-text="`${size} 条`"></option>
                        </template>
                    </select>
                </label>
            </div>

            <div class="mt-4 space-y-3">
                <template x-for="item in users" :key="item.id">
                    <div class="rounded-[1.3rem] border border-bronze/10 bg-parchment/55 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="font-medium text-ink" x-text="item.nickname || item.username"></div>
                                    <span
                                        :class="item.status === 'active' ? 'bg-sage/10 text-sage' : 'bg-rose/10 text-rose'"
                                        class="rounded-full px-2.5 py-1 text-xs"
                                        x-text="item.status === 'active' ? '正常' : '已禁用'"
                                    ></span>
                                </div>
                                <div class="mt-1 text-sm text-ink/55">
                                    手机号 <span x-text="item.phone || '未填写'"></span>
                                    <span class="mx-1">/</span>
                                    OpenID <span x-text="item.openid || '未绑定'"></span>
                                </div>
                                <div class="mt-1 text-sm text-ink/55">
                                    会员 ID <span x-text="item.membership_member_id || '未绑定'"></span>
                                </div>
                            </div>
                            <div class="flex flex-wrap justify-end gap-2">
                                <button @click="openUserModal(item)" class="rounded-full border border-bronze/15 px-3 py-2 text-xs text-bronze">编辑</button>
                                <button @click="resetUserPassword(item)" class="rounded-full border border-teal/15 px-3 py-2 text-xs text-teal">重置密码</button>
                                <button
                                    @click="toggleUserStatus(item)"
                                    :class="item.status === 'active' ? 'border-rose/20 text-rose' : 'border-sage/20 text-sage'"
                                    class="rounded-full border px-3 py-2 text-xs"
                                    x-text="item.status === 'active' ? '禁用' : '启用'"
                                ></button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-bronze/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-ink/55">第 <span x-text="userPager.page"></span> / <span x-text="userPager.total_pages"></span> 页</div>
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="setPagerPage('userPager', userPager.page - 1, 'loadUsers')" :disabled="userPager.page <= 1" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">上一页</button>
                    <template x-for="page in pageNumbers(userPager)" :key="`user-page-${page}`">
                        <button
                            @click="setPagerPage('userPager', page, 'loadUsers')"
                            :class="page === userPager.page ? 'bg-bronze text-white' : 'border border-bronze/15 bg-white text-ink'"
                            class="min-w-10 rounded-full px-3 py-2 text-sm"
                            x-text="page"
                        ></button>
                    </template>
                    <button @click="setPagerPage('userPager', userPager.page + 1, 'loadUsers')" :disabled="userPager.page >= userPager.total_pages" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">下一页</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'members'" class="space-y-6" x-cloak>
        <div class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl text-ink">会员联动</h2>
                    <p class="mt-2 text-sm text-ink/60">会员新增和编辑使用模态框，列表改为分页加载，余额仍然直接联动会员系统数据库。</p>
                </div>
                <button @click="openMemberModal()" class="rounded-full bg-sage px-4 py-2 text-sm text-white">新增会员</button>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-bronze/10 pt-4">
                <div class="text-sm text-ink/55">共 <span x-text="memberPager.total"></span> 个会员</div>
                <label class="flex items-center gap-2 text-sm text-ink/60">
                    每页
                    <select x-model="memberPager.page_size" @change="setPagerSize('memberPager', memberPager.page_size, 'loadMembers')" class="rounded-full border-bronze/15 bg-parchment/55 pr-8">
                        <template x-for="size in pageSizeOptions" :key="`member-size-${size}`">
                            <option :value="size" x-text="`${size} 条`"></option>
                        </template>
                    </select>
                </label>
            </div>

            <div class="mt-4 space-y-3">
                <template x-for="item in members" :key="item.fid">
                    <div class="rounded-[1.3rem] border border-bronze/10 bg-parchment/55 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-medium text-ink" x-text="item.fname"></div>
                                <div class="mt-1 text-sm text-ink/55">
                                    会员编号 <span x-text="item.fnumber"></span>
                                    <span class="mx-1">/</span>
                                    等级 <span x-text="item.fclassesname || '未分级'"></span>
                                </div>
                                <div class="mt-1 text-sm text-ink/55">余额 ¥<span x-text="formatMoney(item.fbalance)"></span></div>
                            </div>
                            <button @click="openMemberModal(item)" class="rounded-full border border-bronze/15 px-3 py-2 text-xs text-bronze">编辑</button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-bronze/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-ink/55">第 <span x-text="memberPager.page"></span> / <span x-text="memberPager.total_pages"></span> 页</div>
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="setPagerPage('memberPager', memberPager.page - 1, 'loadMembers')" :disabled="memberPager.page <= 1" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">上一页</button>
                    <template x-for="page in pageNumbers(memberPager)" :key="`member-page-${page}`">
                        <button
                            @click="setPagerPage('memberPager', page, 'loadMembers')"
                            :class="page === memberPager.page ? 'bg-bronze text-white' : 'border border-bronze/15 bg-white text-ink'"
                            class="min-w-10 rounded-full px-3 py-2 text-sm"
                            x-text="page"
                        ></button>
                    </template>
                    <button @click="setPagerPage('memberPager', memberPager.page + 1, 'loadMembers')" :disabled="memberPager.page >= memberPager.total_pages" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">下一页</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'activities'" class="space-y-6" x-cloak>
        <div class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl text-ink">活动管理</h2>
                    <p class="mt-2 text-sm text-ink/60">活动内容使用独立编辑页，列表保留查看和跳转入口。活动列表暂不分页，当前数据量较小且以配置操作为主。</p>
                </div>
                <button @click="goToActivityEditor()" class="rounded-full bg-bronze px-4 py-2 text-sm text-white">新建活动</button>
            </div>

            <div class="mt-4 space-y-3">
                <template x-for="item in activities" :key="item.id">
                    <div class="rounded-[1.3rem] border border-bronze/10 bg-parchment/55 p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 gap-4">
                                <div class="h-20 w-20 overflow-hidden rounded-[1.2rem] border border-bronze/10 bg-white">
                                    <template x-if="item.thumbnail_image">
                                        <img :src="item.thumbnail_image" alt="活动缩略图" class="h-full w-full object-cover">
                                    </template>
                                    <template x-if="!item.thumbnail_image">
                                        <div class="flex h-full w-full items-center justify-center text-xs text-ink/40">暂无缩略图</div>
                                    </template>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium text-ink" x-text="item.title"></div>
                                    <div class="mt-1 line-clamp-2 text-sm text-ink/55" x-text="item.summary || '暂无摘要'"></div>
                                </div>
                            </div>
                            <button @click="goToActivityEditor(item.id)" class="rounded-full border border-bronze/15 px-3 py-2 text-xs text-bronze">编辑活动</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div x-show="activeTab === 'settings'" class="space-y-6" x-cloak>
        <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">
            <div class="space-y-8 rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card">
                <form @submit.prevent="saveMembershipSettings" class="space-y-4">
                    <h2 class="font-display text-2xl text-ink">会员系统配置</h2>
                    <label class="block text-sm text-ink/70">数据库地址<input x-model="settings.membership_mysql.host" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">端口<input x-model="settings.membership_mysql.port" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">数据库名<input x-model="settings.membership_mysql.database" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">用户名<input x-model="settings.membership_mysql.username" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">密码<input x-model="settings.membership_mysql.password" type="password" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-full bg-bronze px-4 py-2 text-sm text-white">保存</button>
                        <button type="button" @click="testMembership()" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">测试连接</button>
                    </div>
                </form>

                <form @submit.prevent="saveLogSettings" class="space-y-4 border-t border-bronze/10 pt-6">
                    <h2 class="font-display text-2xl text-ink">日志配置</h2>
                    <label class="block text-sm text-ink/70">
                        最低记录级别
                        <select x-model="settings.log.min_level" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                            <template x-for="level in logLevelOptions" :key="`setting-${level}`">
                                <option :value="level" x-text="level"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm text-ink/70">保留天数<input x-model="settings.log.retention_days" type="number" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">单文件大小（MB）<input x-model="settings.log.max_size_mb" type="number" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <button type="submit" class="rounded-full bg-sage px-4 py-2 text-sm text-white">保存日志配置</button>
                </form>
            </div>

            <div class="space-y-8 rounded-[1.8rem] border border-teal/10 bg-white/80 p-5 shadow-card">
                <form @submit.prevent="saveWechatPaySettings" class="space-y-4">
                    <h2 class="font-display text-2xl text-ink">微信支付配置</h2>
                    <label class="block text-sm text-ink/70">AppID<input x-model="settings.wechat_pay.app_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">商户号<input x-model="settings.wechat_pay.merchant_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">商户证书序列号<input x-model="settings.wechat_pay.merchant_serial_no" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">支付模式<select x-model="settings.wechat_pay.pay_mode" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"><option value="JSAPI">JSAPI</option><option value="H5">H5</option></select></label>
                    <label class="block text-sm text-ink/70">通知地址<input x-model="settings.wechat_pay.notify_url" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">APIv3 Key<input x-model="settings.wechat_pay.api_v3_key" type="password" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">商户私钥<textarea x-model="settings.wechat_pay.private_key_content" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></textarea></label>
                    <label class="block text-sm text-ink/70">平台证书<textarea x-model="settings.wechat_pay.platform_cert_content" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></textarea></label>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-full bg-teal px-4 py-2 text-sm text-white">保存支付配置</button>
                        <button type="button" @click="testWechatPay()" class="rounded-full border border-teal/15 px-4 py-2 text-sm text-teal">测试配置</button>
                    </div>
                </form>

                <form @submit.prevent="saveServiceAccountSettings" class="space-y-4 border-t border-bronze/10 pt-6">
                    <h2 class="font-display text-2xl text-ink">公众号配置</h2>
                    <label class="block text-sm text-ink/70">AppID<input x-model="settings.wechat_service_account.app_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <label class="block text-sm text-ink/70">AppSecret<input x-model="settings.wechat_service_account.app_secret" type="password" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-full bg-bronze px-4 py-2 text-sm text-white">保存公众号配置</button>
                        <button type="button" @click="testServiceAccount()" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">测试配置</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card">
            <h2 class="font-display text-2xl text-ink">消息通知配置</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="block text-sm text-ink/70">管理员付款模板 ID<input x-model="settings.notifications.admin_paid_template_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                <label class="block text-sm text-ink/70">管理员取消模板 ID<input x-model="settings.notifications.admin_cancelled_template_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                <label class="block text-sm text-ink/70">用户下单模板 ID<input x-model="settings.notifications.user_created_template_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                <label class="block text-sm text-ink/70">用户付款模板 ID<input x-model="settings.notifications.user_paid_template_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                <label class="block text-sm text-ink/70">用户发货模板 ID<input x-model="settings.notifications.user_shipped_template_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                <label class="block text-sm text-ink/70">用户完成模板 ID<input x-model="settings.notifications.user_completed_template_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                <label class="block text-sm text-ink/70">用户关闭模板 ID<input x-model="settings.notifications.user_closed_template_id" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
            </div>
            <div class="mt-4 flex flex-wrap gap-4 text-sm text-ink/70">
                <label class="flex items-center gap-2"><input x-model="settings.notifications.admin_paid_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-bronze/20">管理员付款通知</label>
                <label class="flex items-center gap-2"><input x-model="settings.notifications.admin_cancelled_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-bronze/20">管理员取消通知</label>
                <label class="flex items-center gap-2"><input x-model="settings.notifications.user_created_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-bronze/20">用户下单通知</label>
                <label class="flex items-center gap-2"><input x-model="settings.notifications.user_paid_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-bronze/20">用户付款通知</label>
                <label class="flex items-center gap-2"><input x-model="settings.notifications.user_shipped_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-bronze/20">用户发货通知</label>
                <label class="flex items-center gap-2"><input x-model="settings.notifications.user_completed_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-bronze/20">用户完成通知</label>
                <label class="flex items-center gap-2"><input x-model="settings.notifications.user_closed_enabled" type="checkbox" true-value="1" false-value="0" class="rounded border-bronze/20">用户关闭通知</label>
            </div>
            <button @click="saveNotificationSettings()" class="mt-5 rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">保存通知配置</button>
        </div>
    </div>

    <div x-show="activeTab === 'logs'" class="rounded-[1.8rem] border border-bronze/10 bg-white/80 p-5 shadow-card" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-3">
                <select x-model="logFilters.level" class="rounded-full border-bronze/15 bg-parchment/55">
                    <option value="">全部日志级别</option>
                    <template x-for="level in logLevelOptions" :key="`filter-level-${level}`">
                        <option :value="level" x-text="level"></option>
                    </template>
                </select>
                <select x-model="logFilters.channel" class="rounded-full border-bronze/15 bg-parchment/55">
                    <option value="">全部日志通道</option>
                    <template x-for="channel in logChannelOptions" :key="`filter-channel-${channel}`">
                        <option :value="channel" x-text="channel"></option>
                    </template>
                </select>
                <button @click="resetLogFilters()" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">重置</button>
            </div>
            <label class="flex items-center gap-2 text-sm text-ink/60">
                每页
                <select x-model="logPager.page_size" @change="setPagerSize('logPager', logPager.page_size, 'loadLogs')" class="rounded-full border-bronze/15 bg-parchment/55 pr-8">
                    <template x-for="size in pageSizeOptions" :key="`log-size-${size}`">
                        <option :value="size" x-text="`${size} 条`"></option>
                    </template>
                </select>
            </label>
        </div>

        <div class="mt-5 space-y-3">
            <template x-for="item in logs" :key="item.id">
                <article class="rounded-[1.3rem] border border-bronze/10 bg-parchment/55 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-medium text-ink"><span x-text="item.level"></span> / <span x-text="item.channel"></span></div>
                        <div class="text-xs text-ink/50" x-text="item.created_at"></div>
                    </div>
                    <div class="mt-2 text-sm text-ink/70" x-text="item.message"></div>
                </article>
            </template>
        </div>

        <div class="mt-6 flex flex-col gap-3 border-t border-bronze/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-ink/55">共 <span x-text="logPager.total"></span> 条日志</div>
            <div class="flex flex-wrap items-center gap-2">
                <button @click="setPagerPage('logPager', logPager.page - 1, 'loadLogs')" :disabled="logPager.page <= 1" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">上一页</button>
                <template x-for="page in pageNumbers(logPager)" :key="`log-page-${page}`">
                    <button
                        @click="setPagerPage('logPager', page, 'loadLogs')"
                        :class="page === logPager.page ? 'bg-bronze text-white' : 'border border-bronze/15 bg-white text-ink'"
                        class="min-w-10 rounded-full px-3 py-2 text-sm"
                        x-text="page"
                    ></button>
                </template>
                <button @click="setPagerPage('logPager', logPager.page + 1, 'loadLogs')" :disabled="logPager.page >= logPager.total_pages" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze disabled:cursor-not-allowed disabled:opacity-40">下一页</button>
            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="modals.category" x-cloak x-transition.opacity.duration.180ms class="modal-overlay" @click.self="closeCategoryModal()">
            <div x-transition.scale.opacity.duration.220ms class="modal-panel w-full max-w-xl overflow-y-auto rounded-[1.9rem] border border-bronze/10 bg-white/95 p-6 shadow-glow">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-2xl text-ink" x-text="categoryForm.id ? '编辑分类' : '新建分类'"></h3>
                        <p class="mt-1 text-sm text-ink/55">父级分类直接从现有分类中选择，层级会自动同步更新。</p>
                    </div>
                    <button @click="closeCategoryModal()" type="button" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze">关闭</button>
                </div>
                <form @submit.prevent="saveCategory" class="mt-5 space-y-4">
                    <label class="block text-sm text-ink/70">
                        分类名称
                        <input x-model="categoryForm.name" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                    </label>
                    <label class="block text-sm text-ink/70">
                        父级分类
                        <select x-model="categoryForm.parent_id" @change="syncCategoryLevel()" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                            <option value="0">作为一级分类</option>
                            <template x-for="item in categoryParentOptions()" :key="`parent-${item.id}`">
                                <option :value="String(item.id)" x-text="categoryLabel(item)"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm text-ink/70">
                        分类层级
                        <input x-model="categoryForm.level" type="number" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" readonly>
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="closeCategoryModal()" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">取消</button>
                        <button type="submit" class="rounded-full bg-sage px-4 py-2 text-sm text-white">保存分类</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="modals.user" x-cloak x-transition.opacity.duration.180ms class="modal-overlay" @click.self="closeUserModal()">
            <div x-transition.scale.opacity.duration.220ms class="modal-panel w-full max-w-xl overflow-y-auto rounded-[1.9rem] border border-bronze/10 bg-white/95 p-6 shadow-glow">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-2xl text-ink" x-text="userForm.id ? '编辑用户' : '新增用户'"></h3>
                        <p class="mt-1 text-sm text-ink/55">手机号不能重复。新增用户时，系统会自动将初始密码设置为手机号后 8 位。</p>
                    </div>
                    <button @click="closeUserModal()" type="button" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze">关闭</button>
                </div>
                <form @submit.prevent="saveUser" class="mt-5 space-y-4">
                    <label class="block text-sm text-ink/70">
                        用户名
                        <input x-model="userForm.username" type="text" autocomplete="off" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                    </label>
                    <label class="block text-sm text-ink/70">
                        昵称
                        <input x-model="userForm.nickname" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                    </label>
                    <label class="block text-sm text-ink/70">
                        手机号
                        <input x-model="userForm.phone" type="text" inputmode="numeric" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                    </label>
                    <div x-show="!userForm.id" class="rounded-[1.3rem] border border-teal/15 bg-teal/5 px-4 py-3 text-sm text-teal">
                        初始密码将自动设置为手机号后 8 位。
                    </div>
                    <label x-show="userForm.id" class="block text-sm text-ink/70">
                        登录密码
                        <input x-model="userForm.password" type="password" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" placeholder="留空则保持当前密码">
                    </label>
                    <label class="block text-sm text-ink/70">
                        绑定会员
                        <input
                            x-model="userMemberKeyword"
                            @input="scheduleMemberSearch()"
                            type="text"
                            class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"
                            placeholder="输入会员编码或名称进行搜索"
                            autocomplete="off"
                        >
                    </label>
                    <div class="rounded-[1.4rem] border border-bronze/10 bg-parchment/45 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="text-sm text-ink/60">
                                当前绑定：
                                <span class="font-medium text-ink" x-text="userForm.membership_member_id || '未绑定会员'"></span>
                            </div>
                            <button x-show="userForm.membership_member_id" @click="clearUserMemberBinding()" type="button" class="rounded-full border border-rose/20 px-3 py-1.5 text-xs text-rose">清空绑定</button>
                        </div>
                        <div class="mt-3 space-y-2" x-show="memberOptions.length">
                            <template x-for="item in memberOptions" :key="`user-member-${item.fid}`">
                                <button @click="selectUserMember(item)" type="button" class="flex w-full items-center justify-between rounded-[1.1rem] border border-bronze/10 bg-white/80 px-3 py-2 text-left transition hover:border-bronze/25 hover:bg-white">
                                    <span class="text-sm text-ink">
                                        <span x-text="item.fname"></span>
                                        <span class="text-ink/55" x-text="`（${item.fnumber}）`"></span>
                                    </span>
                                    <span class="text-xs text-ink/50" x-text="item.fclassesname || '未分级'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="closeUserModal()" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">取消</button>
                        <button type="submit" class="rounded-full bg-sage px-4 py-2 text-sm text-white">保存用户</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="modals.member" x-cloak x-transition.opacity.duration.180ms class="modal-overlay" @click.self="closeMemberModal()">
            <div x-transition.scale.opacity.duration.220ms class="modal-panel w-full max-w-2xl overflow-y-auto rounded-[1.9rem] border border-bronze/10 bg-white/95 p-6 shadow-glow">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-2xl text-ink" x-text="memberForm.id ? '编辑会员' : '新增会员'"></h3>
                        <p class="mt-1 text-sm text-ink/55">会员余额调整会同步写入会员系统明细和日志。</p>
                    </div>
                    <button @click="closeMemberModal()" type="button" class="rounded-full border border-bronze/15 px-3 py-2 text-sm text-bronze">关闭</button>
                </div>
                <form @submit.prevent="saveMember" class="mt-5 space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm text-ink/70">会员编号<input x-model="memberForm.fnumber" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required></label>
                        <label class="block text-sm text-ink/70">会员名称<input x-model="memberForm.fname" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required></label>
                        <label class="block text-sm text-ink/70">
                            会员等级
                            <select x-model="memberForm.fclassesid" @change="fillMemberClassName" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                                <option value="">请选择会员等级</option>
                                <template x-for="cls in memberClasses" :key="cls.fid">
                                    <option :value="String(cls.fid)" x-text="cls.fname"></option>
                                </template>
                            </select>
                        </label>
                        <label class="block text-sm text-ink/70" x-show="!memberForm.id">初始余额<input x-model="memberForm.initial_amount" type="number" step="0.01" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                        <label class="block text-sm text-ink/70" x-show="memberForm.id">当前余额<input x-model="memberForm.fbalance" type="number" step="0.01" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></label>
                    </div>
                    <label class="block text-sm text-ink/70">备注<textarea x-model="memberForm.fmark" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></textarea></label>

                    <div x-show="memberForm.id" class="rounded-[1.5rem] border border-bronze/10 bg-parchment/40 p-4">
                        <div class="text-sm text-ink/60">支持直接调整会员余额，并同步写入会员系统消费明细和日志表。</div>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="block text-sm text-ink/70">调整金额<input x-model="memberForm.adjust_amount" type="number" step="0.01" class="mt-2 w-full rounded-2xl border-bronze/15 bg-white/80"></label>
                            <label class="block text-sm text-ink/70">调整说明<input x-model="memberForm.adjust_mark" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-white/80"></label>
                        </div>
                        <button type="button" @click="adjustMemberBalance(memberForm.id)" class="mt-4 rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">调整余额</button>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="closeMemberModal()" class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze">取消</button>
                        <button type="submit" class="rounded-full bg-sage px-4 py-2 text-sm text-white">保存会员</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</section>
