<?php
$editorData = [
    'product' => $product ?? null,
    'categories' => $categories ?? [],
];
?>
<script>window.PAGE_DATA = <?= json_encode_unicode($editorData) ?>;</script>
<section x-data="adminProductEditorPage()" class="space-y-6 animate-rise">
    <div class="rounded-[1.8rem] border border-bronze/15 bg-white/85 p-5 shadow-glow sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">Product Editor</div>
                <h1 class="mt-3 font-display text-3xl text-ink sm:text-4xl"><?= !empty($product) ? '编辑商品' : '新建商品' ?></h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-ink/65">
                    在这个页面集中维护商品基础信息、封面图、分类和详情内容。保存后会同步更新商品列表与商城前台展示。
                </p>
            </div>
            <a href="/mall/admin?tab=products" class="inline-flex rounded-full border border-bronze/20 px-4 py-2 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">
                返回商品管理
            </a>
        </div>
    </div>

    <form @submit.prevent="saveProduct" class="space-y-6">
        <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <div class="rounded-[1.8rem] border border-bronze/10 bg-white/85 p-5 shadow-card">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-2xl text-ink">商品信息</h2>
                    <span class="rounded-full bg-bronze/10 px-3 py-1 text-xs text-bronze" x-text="productForm.id ? `ID ${productForm.id}` : '新商品'"></span>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm text-ink/70">
                        商品名称
                        <input x-model="productForm.name" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                    </label>
                    <label class="block text-sm text-ink/70">
                        商品简介
                        <input x-model="productForm.summary" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" placeholder="例如：柔雾茶绿与细致流苏，适合春秋叠搭">
                    </label>
                    <label class="block text-sm text-ink/70">
                        品牌
                        <input x-model="productForm.brand" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                    </label>
                    <label class="block text-sm text-ink/70">
                        商品分类
                        <select x-model="productForm.category_id" x-effect="$el.value = String(productForm.category_id || '')" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                            <option value="">请选择分类</option>
                            <template x-for="item in categories" :key="item.id">
                                <option :value="String(item.id)" x-text="categoryLabel(item)"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm text-ink/70">
                        销售价
                        <input x-model="productForm.price" type="number" step="0.01" min="0" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                    </label>
                    <label class="block text-sm text-ink/70">
                        市场价
                        <input x-model="productForm.market_price" type="number" step="0.01" min="0" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                    </label>
                    <label class="block text-sm text-ink/70">
                        总库存
                        <input x-model="productForm.stock_total" type="number" min="0" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                    </label>
                    <label class="block text-sm text-ink/70">
                        评分
                        <input x-model="productForm.rating" type="number" min="0" max="5" step="0.1" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                    </label>
                    <label class="block text-sm text-ink/70">
                        销量
                        <input x-model="productForm.sales_count" type="number" min="0" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                    </label>
                    <label class="block text-sm text-ink/70 md:col-span-2">
                        Quick View 文案
                        <textarea x-model="productForm.quick_view_text" rows="3" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></textarea>
                    </label>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="flex items-center gap-2 rounded-2xl border border-bronze/10 bg-parchment/40 px-4 py-3 text-sm text-ink/70">
                        <input x-model="productForm.is_on_sale" type="checkbox" class="rounded border-bronze/20">
                        立即上架
                    </label>
                    <label class="flex items-center gap-2 rounded-2xl border border-bronze/10 bg-parchment/40 px-4 py-3 text-sm text-ink/70">
                        <input x-model="productForm.support_member_discount" type="checkbox" class="rounded border-bronze/20">
                        参与会员折扣
                    </label>
                    <label class="flex items-center gap-2 rounded-2xl border border-bronze/10 bg-parchment/40 px-4 py-3 text-sm text-ink/70">
                        <input x-model="productForm.is_course" type="checkbox" class="rounded border-bronze/20">
                        课程商品
                    </label>
                    <label class="flex items-center gap-2 rounded-2xl border border-bronze/10 bg-parchment/40 px-4 py-3 text-sm text-ink/70">
                        <input x-model="productForm.is_new_arrival" type="checkbox" class="rounded border-bronze/20">
                        设为上新
                    </label>
                    <label class="flex items-center gap-2 rounded-2xl border border-bronze/10 bg-parchment/40 px-4 py-3 text-sm text-ink/70">
                        <input x-model="productForm.is_recommended_course" type="checkbox" class="rounded border-bronze/20">
                        设为推荐课程
                    </label>
                </div>
            </div>

            <div class="rounded-[1.8rem] border border-teal/10 bg-white/85 p-5 shadow-card">
                <h2 class="font-display text-2xl text-ink">封面图</h2>
                <p class="mt-2 text-sm leading-7 text-ink/60">上传后将直接用于商品列表、详情页首图和快捷预览。</p>

                <div class="mt-5 overflow-hidden rounded-[1.6rem] border border-bronze/10 bg-parchment/50">
                    <template x-if="productForm.cover_image">
                        <img :src="productForm.cover_image" alt="商品封面" class="h-64 w-full object-cover">
                    </template>
                    <template x-if="!productForm.cover_image">
                        <div class="flex h-64 items-center justify-center text-sm text-ink/45">暂未上传封面图</div>
                    </template>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="inline-flex cursor-pointer rounded-full bg-teal px-4 py-2 text-sm text-white shadow-card transition hover:bg-teal/90">
                        <input type="file" accept="image/*" class="hidden" @change="uploadCoverImage($event)">
                        <span x-text="uploading.cover ? '上传中...' : '上传封面图'"></span>
                    </label>
                    <p class="text-xs break-all text-ink/45" x-text="productForm.cover_image || '上传后的图片地址会显示在这里'"></p>
                </div>
            </div>
        </section>

        <section class="rounded-[1.8rem] border border-teal/10 bg-white/85 p-5 shadow-card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-display text-2xl text-ink">商品详情</h2>
                    <p class="mt-2 text-sm leading-7 text-ink/60">详情内容支持预览和富文本编辑两种状态，按需切换即可。</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        x-show="detailMode === 'preview'"
                        type="button"
                        @click="switchDetailMode('edit')"
                        class="rounded-full border border-teal/15 px-4 py-2 text-sm text-teal"
                    >
                        编辑详情
                    </button>
                    <button
                        x-show="detailMode === 'edit'"
                        type="button"
                        @click="switchDetailMode('preview')"
                        class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze"
                    >
                        预览
                    </button>
                </div>
            </div>

            <div x-show="detailMode === 'preview'" class="mt-5" x-cloak>
                <div class="prose prose-stone min-h-[260px] max-w-none rounded-[1.5rem] border border-bronze/10 bg-parchment/45 p-5" x-html="productForm.detail_html || '<p>暂无详情内容</p>'"></div>
            </div>

            <div x-show="detailMode === 'edit'" class="mt-5" x-cloak>
                <textarea id="product-detail-editor-page" class="hidden"></textarea>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="/mall/admin?tab=products" class="rounded-full border border-bronze/20 px-5 py-3 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">
                取消
            </a>
            <button type="submit" class="rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card transition hover:bg-bronze/90">
                保存商品
            </button>
        </div>
    </form>
</section>
