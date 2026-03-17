<section x-data="bindWechatPage()" class="mx-auto max-w-3xl animate-rise">
    <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-6 shadow-glow sm:p-8">
        <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">微信绑定</div>
        <h1 class="mt-3 font-display text-4xl text-ink">登录成功后绑定微信 OpenID 与手机号</h1>
        <p class="mt-4 text-sm leading-7 text-ink/65">
            该页面用于触发微信 OAuth 授权流程。绑定成功后，商城将记录当前用户的 OpenID，
            便于后续发起 JSAPI 支付与公众号模板消息通知。
        </p>
        <div class="mt-6 rounded-[1.5rem] bg-parchment/70 p-5 text-sm leading-7 text-ink/70">
            使用前请先在后台配置公众号 AppID 与 AppSecret，并保证回调地址指向当前商城域名下的 `/mall/api/wechat/callback`。
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <button @click="loadBindUrl" class="rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card">生成授权链接</button>
            <a href="/mall/profile" class="rounded-full border border-bronze/20 px-5 py-3 text-sm text-bronze">返回用户中心</a>
        </div>
        <template x-if="bindUrl">
            <div class="mt-6 rounded-[1.5rem] border border-teal/15 bg-teal/5 p-4 text-sm text-ink/70">
                <div class="font-medium text-ink">点击下方链接完成授权</div>
                <a :href="bindUrl" class="mt-2 block break-all text-teal underline" x-text="bindUrl"></a>
            </div>
        </template>
    </div>
</section>