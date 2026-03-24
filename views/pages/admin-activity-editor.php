<?php
$editorData = [
    'activity' => $activity ?? null,
];
?>
<script>window.PAGE_DATA = <?= json_encode_unicode($editorData) ?>;</script>
<section x-data="adminActivityEditorPage()" class="space-y-6 animate-rise">
    <div class="rounded-[1.8rem] border border-bronze/15 bg-white/85 p-5 shadow-glow sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">Activity Editor</div>
                <h1 class="mt-3 font-display text-3xl text-ink sm:text-4xl"><?= !empty($activity) ? '编辑活动' : '新建活动' ?></h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-ink/65">
                    热门活动会用于导航页和后台推荐位展示，这里统一维护活动标题、摘要、缩略图与富文本内容。
                </p>
            </div>
            <a href="/mall/admin?tab=activities" class="inline-flex rounded-full border border-bronze/20 px-4 py-2 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">
                返回活动管理
            </a>
        </div>
    </div>

    <form @submit.prevent="saveActivity" class="space-y-6">
        <section class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-[1.8rem] border border-bronze/10 bg-white/85 p-5 shadow-card">
                <h2 class="font-display text-2xl text-ink">活动信息</h2>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm text-ink/70 md:col-span-2">
                        标题
                        <input x-model="activityForm.title" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" required>
                    </label>
                    <label class="block text-sm text-ink/70 md:col-span-2">
                        摘要
                        <textarea x-model="activityForm.summary" rows="4" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55"></textarea>
                    </label>
                    <label class="block text-sm text-ink/70 md:col-span-2">
                        跳转链接 URL
                        <input x-model="activityForm.link_url" type="text" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55" placeholder="例如：https://example.com/activity 或 /mall/products/5">
                        <span class="mt-2 block text-xs text-ink/45">配置后，用户点击活动时会优先跳转到该链接，不再进入活动详情页。</span>
                    </label>
                    <label class="block text-sm text-ink/70">
                        展示顺序
                        <input x-model="activityForm.display_order" type="number" min="0" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                    </label>
                    <label class="flex items-center gap-2 rounded-2xl border border-bronze/10 bg-parchment/40 px-4 py-3 text-sm text-ink/70">
                        <input x-model="activityForm.is_active" type="checkbox" class="rounded border-bronze/20">
                        当前启用
                    </label>
                    <label class="block text-sm text-ink/70">
                        开始时间
                        <input x-model="activityForm.starts_at" type="datetime-local" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                    </label>
                    <label class="block text-sm text-ink/70">
                        结束时间
                        <input x-model="activityForm.ends_at" type="datetime-local" class="mt-2 w-full rounded-2xl border-bronze/15 bg-parchment/55">
                    </label>
                </div>
            </div>

            <div class="rounded-[1.8rem] border border-teal/10 bg-white/85 p-5 shadow-card">
                <h2 class="font-display text-2xl text-ink">缩略图</h2>
                <p class="mt-2 text-sm leading-7 text-ink/60">上传后的图片会出现在导航页和活动卡片中。</p>

                <div class="mt-5 overflow-hidden rounded-[1.6rem] border border-bronze/10 bg-parchment/50">
                    <template x-if="activityForm.thumbnail_image">
                        <img :src="activityForm.thumbnail_image" alt="活动缩略图" class="h-64 w-full object-cover">
                    </template>
                    <template x-if="!activityForm.thumbnail_image">
                        <div class="flex h-64 items-center justify-center text-sm text-ink/45">暂未上传缩略图</div>
                    </template>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="inline-flex cursor-pointer rounded-full bg-teal px-4 py-2 text-sm text-white shadow-card transition hover:bg-teal/90">
                        <input type="file" accept="image/*" class="hidden" @change="uploadThumbnailImage($event)">
                        <span x-text="uploading.thumbnail ? '上传中...' : '上传缩略图'"></span>
                    </label>
                    <p class="text-xs break-all text-ink/45" x-text="activityForm.thumbnail_image || '上传后的图片地址会显示在这里'"></p>
                </div>
            </div>
        </section>

        <section class="rounded-[1.8rem] border border-teal/10 bg-white/85 p-5 shadow-card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-display text-2xl text-ink">活动详情</h2>
                    <p class="mt-2 text-sm leading-7 text-ink/60">默认使用预览视图查看内容，进入编辑状态后再显示富文本编辑器。</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        x-show="detailMode === 'edit'"
                        type="button"
                        @click="toggleDetailViewportMode()"
                        class="rounded-full border border-bronze/15 px-4 py-2 text-sm text-bronze"
                        x-text="detailViewportMode === 'ratio' ? '16:9' : '自适应'"
                    ></button>
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
                <div class="rich-content-body prose prose-stone min-h-[260px] max-w-none rounded-[1.5rem] border border-bronze/10 bg-parchment/45 p-5" x-html="activityForm.content_html || '<p>暂无活动内容</p>'"></div>
            </div>

            <div x-show="detailMode === 'edit'" x-ref="detailEditorHost" class="rich-text-editor-host mt-5" x-cloak>
                <textarea id="activity-detail-editor-page" class="hidden"></textarea>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="/mall/admin?tab=activities" class="rounded-full border border-bronze/20 px-5 py-3 text-sm text-bronze transition hover:border-bronze hover:bg-bronze/5">
                取消
            </a>
            <button type="submit" class="rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card transition hover:bg-bronze/90">
                保存活动
            </button>
        </div>
    </form>
</section>
