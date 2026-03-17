<section class="mx-auto max-w-lg animate-rise">
    <div class="rounded-[2rem] border border-bronze/20 bg-white/85 p-6 shadow-glow sm:p-8">
        <div class="text-center">
            <div class="text-sm uppercase tracking-[0.35em] text-bronze/70">用户登录</div>
            <h1 class="mt-3 font-display text-4xl text-ink">登录后开启购物与会员联动</h1>
            <p class="mt-3 text-sm leading-7 text-ink/65">
                游客可以浏览商城，但加入购物车、下单、管理地址、绑定微信与支付功能都需要先登录。
                演示环境默认提供管理员和普通用户账号，可在部署后由管理员统一维护用户。
            </p>
        </div>

        <form x-data="loginPage()" @submit.prevent="submit" class="mt-8 space-y-5">
            <label class="block text-sm text-ink/70">
                用户名
                <input x-model="form.username" type="text" class="mt-2 w-full rounded-[1.2rem] border-bronze/15 bg-parchment/50" placeholder="请输入用户名">
            </label>
            <label class="block text-sm text-ink/70">
                密码
                <input x-model="form.password" type="password" class="mt-2 w-full rounded-[1.2rem] border-bronze/15 bg-parchment/50" placeholder="请输入密码">
            </label>
            <button type="submit" class="w-full rounded-full bg-bronze px-5 py-3 text-sm text-white shadow-card transition hover:bg-bronze/90">登录</button>
        </form>

        <div class="mt-6 rounded-[1.3rem] bg-sage/8 p-4 text-sm leading-7 text-ink/65">
            管理员可在后台新增用户并绑定会员系统会员 ID，商城不会独立保存会员等级与余额，只保留商城账户与会员映射关系。
        </div>
    </div>
</section>