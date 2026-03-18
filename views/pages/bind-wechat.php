<section x-data="bindWechatPage()" class="mx-auto max-w-3xl animate-rise">
    <div class="rounded-[2rem] border border-bronze/15 bg-white/80 p-6 shadow-glow sm:p-8">
        <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">微信绑定</div>
        <h1 class="mt-3 font-display text-4xl text-ink">绑定当前微信与商城账号</h1>
        <p class="mt-4 text-sm leading-7 text-ink/65">
            该页面会自动识别当前微信身份。绑定成功后，商城将记录当前账号的 OpenID，
            便于后续使用微信登录、发起 JSAPI 支付与公众号模板消息通知。
        </p>
        <div class="mt-6 rounded-[1.5rem] bg-parchment/70 p-5 text-sm leading-7 text-ink/70">
            <div>当前账号手机号：<span class="font-medium text-ink"><?= htmlspecialchars((string) ($currentUser['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="mt-2">
                当前绑定状态：
                <span class="font-medium text-ink" x-text="status.userHasOpenid ? (status.userOpenidMasked || '已绑定') : '未绑定'"></span>
            </div>
            <div class="mt-2">
                当前微信识别：
                <span class="font-medium text-ink" x-text="status.oauthOpenidReady ? (status.oauthOpenidMasked || '已识别') : (status.isWechatClient ? '识别中或未识别' : '请在微信客户端中打开')"></span>
            </div>
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <button
                x-show="!status.userHasOpenid"
                x-cloak
                @click="bindCurrentWechat"
                type="button"
                class="rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card"
            >绑定当前微信</button>
            <button
                x-show="status.userHasOpenid"
                x-cloak
                @click="unbindCurrentWechat"
                type="button"
                class="rounded-full border border-rose/25 px-5 py-3 text-sm text-rose shadow-card"
            >解除微信绑定</button>
            <button
                x-show="status.isWechatClient && !status.oauthOpenidReady"
                x-cloak
                @click="retryWechatOauth"
                type="button"
                class="rounded-full border border-teal/20 px-5 py-3 text-sm text-teal shadow-card"
            >重新获取微信身份</button>
            <a href="/mall/profile" class="rounded-full border border-bronze/20 px-5 py-3 text-sm text-bronze">返回用户中心</a>
        </div>
        <div x-show="status.oauthOpenidBoundToOtherUser && !status.currentWechatMatchesUser" x-cloak class="mt-6 rounded-[1.5rem] border border-rose/15 bg-rose/5 p-4 text-sm leading-7 text-rose">
            当前微信已绑定其他账号，无法直接绑定到当前账号，请先在原账号中解除绑定。
        </div>
        <div x-show="status.currentWechatMatchesUser" x-cloak class="mt-6 rounded-[1.5rem] border border-sage/15 bg-sage/5 p-4 text-sm leading-7 text-sage">
            当前微信已与本账号绑定，可直接使用微信登录和微信支付。
        </div>
    </div>
</section>
