(() => {
    const bootstrap = window.MALL_BOOTSTRAP || {};
    const pageData = window.PAGE_DATA || {};
    const tinymceApiKey = 'esazkmyz3gahrj2teqtvimt91wlrracqp3k2ig8zuushd1n6';
    const tinymceScriptSrc = `https://cdn.tiny.cloud/1/${tinymceApiKey}/tinymce/7/tinymce.min.js`;
    const wechatJssdkScriptSrc = 'https://res.wx.qq.com/open/js/jweixin-1.6.0.js';

    const getCsrfToken = () => bootstrap.csrfToken || '';
    const formatMoney = (value) => Number(value || 0).toFixed(2);
    const queryParam = (key) => new URLSearchParams(window.location.search).get(key);

    const loadScriptOnce = (src) => new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });

    const waitForWeixinBridge = () => new Promise((resolve) => {
        if (window.WeixinJSBridge) {
            resolve(window.WeixinJSBridge);
            return;
        }

        const handleReady = () => {
            document.removeEventListener('WeixinJSBridgeReady', handleReady);
            resolve(window.WeixinJSBridge || null);
        };

        document.addEventListener('WeixinJSBridgeReady', handleReady, { once: true });
        window.setTimeout(() => {
            document.removeEventListener('WeixinJSBridgeReady', handleReady);
            resolve(window.WeixinJSBridge || null);
        }, 1200);
    });

    const invokeWechatShareBridge = async (shareData = {}) => {
        const bridge = window.WeixinJSBridge || await waitForWeixinBridge();
        if (!bridge || typeof bridge.invoke !== 'function') {
            throw new Error('当前微信版本暂不支持快捷分享。');
        }

        return new Promise((resolve, reject) => {
            bridge.invoke('sendAppMessage', {
                appid: '',
                img_url: shareData.imgUrl || '',
                img_width: '120',
                img_height: '120',
                link: shareData.link || window.location.href.split('#')[0],
                desc: shareData.desc || '',
                title: shareData.title || document.title,
                type: 'link',
            }, (result = {}) => {
                const errMsg = String(result.err_msg || '');
                if (!errMsg || /send_app_msg:(ok|confirm)/i.test(errMsg)) {
                    resolve(result);
                    return;
                }
                if (/cancel/i.test(errMsg)) {
                    reject(new Error('已取消分享。'));
                    return;
                }
                reject(new Error('快捷分享未成功。'));
            });
        });
    };

    const ensureTinyMce = () => loadScriptOnce(tinymceScriptSrc);
    const uniqueList = (items = []) => items.filter(Boolean).filter((item, index, array) => array.indexOf(item) === index);
    const absoluteUrl = (value = '') => {
        if (!value) {
            return '';
        }
        try {
            return new URL(value, window.location.origin).toString();
        } catch (error) {
            return value;
        }
    };
    const touchPointX = (event) => event.changedTouches?.[0]?.clientX ?? event.touches?.[0]?.clientX ?? 0;
    const touchPointY = (event) => event.changedTouches?.[0]?.clientY ?? event.touches?.[0]?.clientY ?? 0;
    const inventoryText = (stock = 0, supportsMemberDiscount = 0) => {
        const normalizedStock = Math.max(0, Number(stock || 0));
        return Number(supportsMemberDiscount || 0) === 1
            ? `库存 ${normalizedStock} / 会员折扣`
            : `库存 ${normalizedStock}`;
    };
    const memberDiscountRate = (member = bootstrap.currentMember || null) => {
        const raw = Number(member?.foff || 1);
        if (!Number.isFinite(raw) || raw <= 0) {
            return 1;
        }
        if (raw > 1 && raw <= 10) {
            return Number((raw / 10).toFixed(2));
        }
        return raw > 1 ? 1 : Number(raw.toFixed(2));
    };
    const hasMemberDiscount = (supportsMemberDiscount = 0) => {
        return Boolean(bootstrap.currentUser && memberDiscountRate() < 1 && Number(supportsMemberDiscount || 0) === 1);
    };
    const buildCheckoutUrl = (mode, params = {}) => {
        const search = new URLSearchParams({ mode, ...params });
        return `/mall/checkout?${search.toString()}`;
    };
    const orderStatusLabel = (order = {}) => {
        const status = order?.status || '';
        const paymentStatus = order?.payment_status || '';
        if (status === 'pending_payment') {
            return paymentStatus === 'paid' ? '待发货' : '待付款';
        }
        if (status === 'pending_shipment') {
            return '待发货';
        }
        if (status === 'pending_receipt') {
            return '待收货';
        }
        if (status === 'completed') {
            return '已完成';
        }
        if (status === 'closed') {
            return '已关闭';
        }
        return status || '处理中';
    };
    const paymentMethodLabel = (order = {}) => {
        const method = order?.payment_method || '';
        if (method === 'balance') {
            return '会员余额支付';
        }
        if (method === 'wechat') {
            return '微信支付';
        }
        if (order?.payment_status === 'paid') {
            return '已支付';
        }
        return '未支付';
    };
    const orderResultTitle = (order = null) => {
        if (!order) {
            return '支付完成后可在这里查看订单状态';
        }
        if (order.payment_status === 'paid') {
            return '支付成功';
        }
        if (order.status === 'closed') {
            return '支付失败或订单已关闭';
        }
        return '支付未完成';
    };
    const adminOrderCanShip = (order = {}) => {
        return (order?.status || '') === 'pending_shipment' && (order?.payment_status || '') === 'paid';
    };
    const adminOrderCanClose = (order = {}) => {
        return ['pending_payment', 'pending_shipment'].includes(order?.status || '');
    };
    const orderReceiverAddress = (order = {}) => {
        const direct = String(order?.receiver_address || '').trim();
        if (direct) {
            return direct;
        }
        const address = order?.address_snapshot || {};
        return [address.province, address.city, address.district, address.detail_address]
            .map((value) => String(value || '').trim())
            .filter(Boolean)
            .join(' ');
    };
    const orderItemSpecLabel = (item = {}) => {
        const skuName = String(item?.sku_name || '').trim();
        if (skuName) {
            return skuName;
        }
        const attributes = Array.isArray(item?.attributes)
            ? item.attributes
            : Object.values(item?.attributes || {});
        return attributes.map((value) => String(value || '').trim()).filter(Boolean).join(' / ');
    };
    const orderClosedReasonLabel = (order = {}) => {
        const reason = String(order?.closed_reason || '').trim();
        if (!reason) {
            return '未提供';
        }
        if (reason === 'user_cancelled') {
            return '用户主动取消';
        }
        if (reason === 'admin_closed') {
            return '管理员取消订单';
        }
        if (reason === 'timeout') {
            return '订单支付超时自动关闭';
        }
        return reason;
    };
    const orderHasShippingInfo = (order = {}) => {
        return [order?.shipping_company, order?.shipping_no, order?.shipped_at]
            .map((value) => String(value || '').trim())
            .some(Boolean);
    };
    const orderHasCloseInfo = (order = {}) => {
        return [order?.closed_reason, order?.closed_at]
            .map((value) => String(value || '').trim())
            .some(Boolean) || String(order?.status || '').trim() === 'closed';
    };
    const copyText = async (value, successMessage = '已复制。') => {
        const text = String(value || '').trim();
        if (!text) {
            notice('没有可复制的内容。', 'error');
            return;
        }

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
            notice(successMessage);
        } catch (error) {
            notice('复制失败，请手动复制。', 'error');
        }
    };

    const fallbackBackPath = (pathname) => {
        if (pathname.startsWith('/mall/admin/products/edit')) {
            return '/mall/admin?tab=products';
        }
        if (pathname.startsWith('/mall/admin/activities/edit')) {
            return '/mall/admin?tab=activities';
        }
        if (pathname.startsWith('/mall/admin')) {
            return '/mall';
        }
        if (pathname.startsWith('/mall/products/')) {
            return '/mall';
        }
        if (pathname.startsWith('/mall/profile') || pathname.startsWith('/mall/cart') || pathname.startsWith('/mall/checkout') || pathname.startsWith('/mall/login')) {
            return '/mall';
        }
        if (pathname.startsWith('/mall')) {
            return '/portal';
        }
        return '/mall';
    };

    const notice = (message, type = 'success') => {
        if (!message) {
            return;
        }

        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }

        const item = document.createElement('div');
        item.className = `toast-item ${type}`;
        item.textContent = message;
        stack.appendChild(item);
        window.setTimeout(() => item.remove(), 2800);
    };

    const ADMIN_UPLOAD_MAX_BYTES = 1536 * 1024;
    const ADMIN_UPLOAD_MAX_DIMENSION = 1800;
    const ADMIN_UPLOAD_TARGET_BYTES = 1280 * 1024;
    const replaceFileExtension = (name, nextExtension) => {
        const safeName = String(name || 'image').trim() || 'image';
        const normalized = safeName.replace(/\.[^.]+$/, '');
        return `${normalized}.${nextExtension}`;
    };
    const loadImageFromBlob = (blob) => new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(blob);
        const image = new Image();
        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('图片读取失败，请重新选择。'));
        };
        image.src = objectUrl;
    });
    const canvasToBlob = (canvas, type, quality) => new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
                return;
            }
            reject(new Error('图片处理失败，请重试。'));
        }, type, quality);
    });
    const optimizeAdminUploadImage = async (file, preferredName = 'image.png') => {
        if (!(file instanceof Blob)) {
            return { blob: file, filename: preferredName };
        }

        const originalName = file instanceof File && file.name ? file.name : preferredName;
        const mimeType = String(file.type || '').toLowerCase();
        const canOptimize = /^image\/(png|jpe?g|webp)$/i.test(mimeType);

        if (file.size <= ADMIN_UPLOAD_MAX_BYTES || !canOptimize) {
            return { blob: file, filename: originalName };
        }

        const image = await loadImageFromBlob(file);
        const naturalWidth = Math.max(1, Number(image.naturalWidth || image.width || 1));
        const naturalHeight = Math.max(1, Number(image.naturalHeight || image.height || 1));
        let scale = Math.min(1, ADMIN_UPLOAD_MAX_DIMENSION / Math.max(naturalWidth, naturalHeight));
        let quality = 0.86;
        let output = null;

        for (let attempt = 0; attempt < 6; attempt += 1) {
            const width = Math.max(1, Math.round(naturalWidth * scale));
            const height = Math.max(1, Math.round(naturalHeight * scale));
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            if (!context) {
                throw new Error('图片处理失败，请重试。');
            }

            context.drawImage(image, 0, 0, width, height);
            output = await canvasToBlob(canvas, 'image/jpeg', quality);
            if (output.size <= ADMIN_UPLOAD_MAX_BYTES) {
                break;
            }

            quality = Math.max(0.58, quality - 0.08);
            scale *= output.size > ADMIN_UPLOAD_TARGET_BYTES ? 0.82 : 0.9;
        }

        if (!output || output.size > ADMIN_UPLOAD_MAX_BYTES) {
            throw new Error('图片体积过大，请压缩后重试。');
        }

        return {
            blob: output,
            filename: replaceFileExtension(originalName, 'jpg'),
        };
    };
    const richTextEditorContentStyle = () => `
        body {
            margin: 0;
            padding: 16px;
            color: #2f2419;
            background: #fffaf4;
            font-family: "Microsoft YaHei", "PingFang SC", sans-serif;
            font-size: 15px;
            line-height: 1.85;
        }
        p {
            margin: 0 0 1em;
        }
        p:last-child {
            margin-bottom: 0;
        }
        p:has(> img:only-child),
        p:has(> a > img:only-child) {
            margin-top: 0;
            margin-bottom: 0;
        }
        p:has(> img:only-child) + p:has(> img:only-child),
        p:has(> a > img:only-child) + p:has(> a > img:only-child) {
            margin-top: 0;
        }
        img {
            display: block;
            max-width: 100%;
            height: auto;
            margin: 0;
            border-radius: 16px;
        }
        a {
            color: #9c6737;
        }
        ul, ol {
            margin: 0 0 1em;
            padding-left: 1.4em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 1em;
        }
        th, td {
            border: 1px solid rgba(156, 103, 55, 0.2);
            padding: 0.6em 0.75em;
            vertical-align: top;
        }
        blockquote {
            margin: 0 0 1em;
            padding-left: 1em;
            border-left: 3px solid rgba(156, 103, 55, 0.25);
            color: rgba(47, 36, 25, 0.72);
        }
        figure {
            margin: 0;
        }
    `;
    const computeRichTextEditorHeight = (host, viewportMode = 'ratio') => {
        const minimum = 840;
        const hostWidth = Math.max(0, Number(host?.clientWidth || 0));
        if (viewportMode === 'fluid') {
            return Math.max(minimum, window.innerHeight - 220);
        }
        const ratioHeight = hostWidth > 0 ? Math.round((hostWidth * 9) / 16) : minimum;
        return Math.max(minimum, ratioHeight);
    };
    const applyRichTextEditorHeight = (editor, host, viewportMode = 'ratio') => {
        if (!editor || !host) {
            return;
        }

        const height = computeRichTextEditorHeight(host, viewportMode);
        host.style.setProperty('--rich-editor-height', `${height}px`);
        const container = editor.getContainer?.();
        if (container) {
            container.style.height = `${height}px`;
            container.style.minHeight = `${height}px`;
        }

        if (typeof editor.theme?.resizeTo === 'function') {
            try {
                editor.theme.resizeTo(null, height);
            } catch (error) {
                // noop
            }
        }
    };
    const richTextFullscreenBodyClass = 'rich-text-fullscreen-active';
    const isRichTextEditorFullscreen = (editor) => {
        const container = editor?.getContainer?.();
        if (!container) {
            return false;
        }

        return document.fullscreenElement === container || container.classList.contains('rich-text-editor-fallback-fullscreen');
    };
    const syncRichTextEditorFullscreen = (editor, host, viewportMode = 'ratio') => {
        const container = editor?.getContainer?.();
        const active = isRichTextEditorFullscreen(editor);
        document.body.classList.toggle(richTextFullscreenBodyClass, active);
        host?.classList.toggle('rich-text-editor-host--fullscreen', active);
        container?.classList.toggle('rich-text-editor-native-fullscreen', active);

        if (active) {
            const height = Math.max(window.innerHeight, 840);
            host?.style.setProperty('--rich-editor-height', `${height}px`);
            if (container) {
                container.style.height = `${height}px`;
                container.style.minHeight = `${height}px`;
            }
            if (typeof editor.theme?.resizeTo === 'function') {
                try {
                    editor.theme.resizeTo(null, height);
                } catch (error) {
                    // noop
                }
            }
        } else {
            applyRichTextEditorHeight(editor, host, viewportMode);
        }
    };
    const requestRichTextEditorFullscreen = async (target) => {
        if (!target) {
            return false;
        }

        if (typeof target.requestFullscreen === 'function') {
            await target.requestFullscreen();
            return true;
        }

        const webkitRequestFullscreen = target.webkitRequestFullscreen || target.webkitEnterFullscreen;
        if (typeof webkitRequestFullscreen === 'function') {
            webkitRequestFullscreen.call(target);
            return true;
        }

        return false;
    };
    const exitRichTextEditorFullscreen = async () => {
        if (document.fullscreenElement && typeof document.exitFullscreen === 'function') {
            await document.exitFullscreen();
            return true;
        }

        if (typeof document.webkitExitFullscreen === 'function') {
            document.webkitExitFullscreen();
            return true;
        }

        return false;
    };
    const toggleRichTextEditorFullscreen = async (editor, host, viewportMode = 'ratio') => {
        const container = editor?.getContainer?.();
        if (!container) {
            return;
        }

        if (isRichTextEditorFullscreen(editor)) {
            container.classList.remove('rich-text-editor-fallback-fullscreen');
            await exitRichTextEditorFullscreen();
            syncRichTextEditorFullscreen(editor, host, viewportMode);
            return;
        }

        container.classList.remove('rich-text-editor-fallback-fullscreen');
        try {
            const enteredNativeFullscreen = await requestRichTextEditorFullscreen(container);
            if (!enteredNativeFullscreen) {
                container.classList.add('rich-text-editor-fallback-fullscreen');
            }
        } catch (error) {
            container.classList.add('rich-text-editor-fallback-fullscreen');
        }
        syncRichTextEditorFullscreen(editor, host, viewportMode);
    };
    const createRichTextEditor = async ({
        selector,
        editorId,
        initialContent = '',
        onChange = () => {},
        getViewportMode = () => 'ratio',
        getHost = () => null,
    }) => {
        await ensureTinyMce();
        const existingEditor = window.tinymce?.get(editorId);
        if (existingEditor) {
            existingEditor.setContent(initialContent || '');
            window.requestAnimationFrame(() => applyRichTextEditorHeight(existingEditor, getHost(), getViewportMode()));
            return existingEditor;
        }

        const result = await window.tinymce?.init({
            selector,
            height: 840,
            menubar: false,
            plugins: 'lists link image table code',
            toolbar: 'undo redo | styles | bold italic | alignleft aligncenter alignright | bullist numlist | image link table | appfullscreen code',
            automatic_uploads: true,
            paste_data_images: true,
            convert_urls: false,
            relative_urls: false,
            remove_script_host: false,
            branding: false,
            promotion: false,
            content_style: richTextEditorContentStyle(),
            setup: (editor) => {
                const getCurrentHost = () => getHost();
                const getCurrentViewportMode = () => getViewportMode();
                const notifyFullscreenStateChange = () => {
                    editor.dispatch('RichTextFullscreenStateChange', {
                        state: isRichTextEditorFullscreen(editor),
                    });
                };
                editor.ui.registry.addToggleButton('appfullscreen', {
                    icon: 'fullscreen',
                    tooltip: '全屏',
                    onAction: () => {
                        toggleRichTextEditorFullscreen(editor, getCurrentHost(), getCurrentViewportMode())
                            .finally(() => notifyFullscreenStateChange());
                    },
                    onSetup: (buttonApi) => {
                        const refresh = () => {
                            buttonApi.setActive(isRichTextEditorFullscreen(editor));
                        };
                        const handleFullscreenChange = () => {
                            syncRichTextEditorFullscreen(editor, getCurrentHost(), getCurrentViewportMode());
                            refresh();
                        };
                        editor.on('RichTextFullscreenStateChange', refresh);
                        document.addEventListener('fullscreenchange', handleFullscreenChange);
                        document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
                        refresh();
                        return () => {
                            editor.off('RichTextFullscreenStateChange', refresh);
                            document.removeEventListener('fullscreenchange', handleFullscreenChange);
                            document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
                        };
                    },
                });
                editor.on('init', () => {
                    editor.setContent(initialContent || '');
                    window.requestAnimationFrame(() => applyRichTextEditorHeight(editor, getHost(), getViewportMode()));
                });
                editor.on('change keyup input undo redo SetContent', () => {
                    onChange(editor.getContent());
                });
                editor.on('remove', () => {
                    const container = editor.getContainer?.();
                    container?.classList.remove('rich-text-editor-fallback-fullscreen', 'rich-text-editor-native-fullscreen');
                    document.body.classList.remove(richTextFullscreenBodyClass);
                    getHost()?.classList.remove('rich-text-editor-host--fullscreen');
                });
            },
            images_upload_handler: editorUploadHandler,
        });

        return Array.isArray(result) ? result[0] : result;
    };

    const isWechatUserAgent = () => /MicroMessenger/i.test(window.navigator.userAgent || '');
    const wechatOauthAttemptKey = (scene) => `wechat-oauth-attempted:${scene}`;
    const hasWechatOauthAttempted = (scene) => {
        try {
            return window.sessionStorage.getItem(wechatOauthAttemptKey(scene)) === '1';
        } catch (error) {
            return false;
        }
    };
    const markWechatOauthAttempted = (scene) => {
        try {
            window.sessionStorage.setItem(wechatOauthAttemptKey(scene), '1');
        } catch (error) {
            // ignore
        }
    };
    const clearWechatOauthAttempted = (scene) => {
        try {
            window.sessionStorage.removeItem(wechatOauthAttemptKey(scene));
        } catch (error) {
            // ignore
        }
    };
    const defaultWechatStatus = () => ({
        isWechatClient: isWechatUserAgent(),
        oauthOpenidReady: false,
        oauthOpenidMasked: '',
        userHasOpenid: Boolean(bootstrap.currentUser?.openid),
        userOpenidMasked: '',
        currentWechatMatchesUser: false,
        oauthOpenidBoundToOtherUser: false,
        canBindCurrentWechat: false,
        canUnbindCurrentWechat: Boolean(bootstrap.currentUser?.openid),
    });
    const mapWechatStatus = (payload = {}) => ({
        isWechatClient: Boolean(payload.is_wechat_client),
        oauthOpenidReady: Boolean(payload.oauth_openid_ready),
        oauthOpenidMasked: payload.oauth_openid_masked || '',
        userHasOpenid: Boolean(payload.user_has_openid),
        userOpenidMasked: payload.user_openid_masked || '',
        currentWechatMatchesUser: Boolean(payload.current_wechat_matches_user),
        oauthOpenidBoundToOtherUser: Boolean(payload.oauth_openid_bound_to_other_user),
        canBindCurrentWechat: Boolean(payload.can_bind_current_wechat),
        canUnbindCurrentWechat: Boolean(payload.can_unbind_current_wechat),
    });
    const fetchWechatStatus = async () => {
        const data = await apiRequest('/mall/api/wechat/status');
        return { ...defaultWechatStatus(), ...mapWechatStatus(data) };
    };
    const startWechatOauth = async (scene, returnUrl = `${window.location.pathname}${window.location.search}`) => {
        markWechatOauthAttempted(scene);
        const query = new URLSearchParams({ scene, return_url: returnUrl });
        const data = await apiRequest(`/mall/api/wechat/oauth-url?${query.toString()}`);
        if (!data.authorize_url) {
            throw new Error('未生成微信授权地址。');
        }
        window.location.href = data.authorize_url;
    };

    const syncHeaderOffset = () => {
        const header = document.querySelector('header');
        const offset = header ? Math.ceil(header.getBoundingClientRect().height) + 16 : 80;
        document.documentElement.style.setProperty('--mall-header-offset', `${offset}px`);
    };

    const bindBackNavigation = () => {
        document.querySelectorAll('[data-nav-back]').forEach((button) => {
            if (button.dataset.bound === '1') {
                return;
            }

            button.dataset.bound = '1';
            button.addEventListener('click', () => {
                const referrer = document.referrer || '';
                const hasInternalHistory = window.history.length > 1 && referrer.startsWith(window.location.origin);
                if (hasInternalHistory) {
                    window.history.back();
                    return;
                }
                window.location.href = fallbackBackPath(window.location.pathname);
            });
        });
    };

    const syncBackToTopButton = () => {
        const button = document.querySelector('[data-back-to-top]');
        if (!button) {
            return;
        }

        button.classList.toggle('is-visible', window.scrollY > 240);
    };

    const bindBackToTop = () => {
        const button = document.querySelector('[data-back-to-top]');
        if (!button || button.dataset.bound === '1') {
            syncBackToTopButton();
            return;
        }

        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        syncBackToTopButton();
    };

    const shouldShowFloatingCartButton = () => {
        const pathname = window.location.pathname;
        if (window.innerWidth > 640) {
            return false;
        }
        if (!pathname.startsWith('/mall')) {
            return false;
        }
        return !pathname.startsWith('/mall/admin')
            && !pathname.startsWith('/mall/cart')
            && !pathname.startsWith('/mall/checkout')
            && !pathname.startsWith('/mall/login')
            && !pathname.startsWith('/mall/profile');
    };

    const cartItemCount = (cart = {}) => {
        return (cart.items || []).reduce((total, item) => total + Number(item.quantity || 0), 0);
    };

    const setFloatingCartCount = (count = 0) => {
        const badge = document.querySelector('[data-floating-cart-count]');
        if (!badge) {
            return;
        }

        const normalized = Math.max(0, Number(count || 0));
        badge.textContent = normalized > 99 ? '99+' : String(normalized);
        badge.classList.toggle('is-empty', normalized === 0);
    };

    const pulseFloatingCartBadge = () => {
        const badge = document.querySelector('[data-floating-cart-count]');
        if (!badge) {
            return;
        }

        badge.classList.remove('is-pulsing');
        void badge.offsetWidth;
        badge.classList.add('is-pulsing');
        window.clearTimeout(Number(badge.dataset.pulseTimer || 0));
        badge.dataset.pulseTimer = String(window.setTimeout(() => {
            badge.classList.remove('is-pulsing');
        }, 420));
    };

    const syncFloatingCartButton = () => {
        const button = document.querySelector('[data-floating-cart]');
        if (!button) {
            return;
        }

        button.classList.toggle('is-visible', shouldShowFloatingCartButton());
        button.setAttribute('href', bootstrap.currentUser ? '/mall/cart' : '/mall/login');
    };

    const refreshFloatingCartCount = async (nextCount = null) => {
        syncFloatingCartButton();
        if (nextCount !== null) {
            setFloatingCartCount(nextCount);
            return;
        }

        if (!bootstrap.currentUser) {
            setFloatingCartCount(0);
            return;
        }

        try {
            const cart = await apiRequest('/mall/api/cart');
            setFloatingCartCount(cartItemCount(cart));
        } catch (error) {
            setFloatingCartCount(0);
        }
    };

    const apiRequest = async (url, options = {}) => {
        const method = (options.method || 'GET').toUpperCase();
        const fetchOptions = {
            method,
            headers: {
                Accept: 'application/json',
                ...(options.headers || {}),
            },
        };

        if (options.body instanceof FormData) {
            fetchOptions.body = options.body;
            fetchOptions.headers['X-CSRF-TOKEN'] = getCsrfToken();
        } else if (options.body !== undefined) {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.headers['X-CSRF-TOKEN'] = getCsrfToken();
            fetchOptions.body = JSON.stringify({
                ...options.body,
                _csrf_token: getCsrfToken(),
            });
        }

        const response = await fetch(url, fetchOptions);
        const text = await response.text();

        let payload;
        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error('服务端返回了无法解析的响应。');
        }

        if (!response.ok || payload.success === false) {
            const requestError = new Error(payload.message || payload.error || '请求失败。');
            requestError.errorCode = payload.error_code || '';
            requestError.data = payload.data ?? null;
            requestError.status = response.status;
            throw requestError;
        }

        return payload.data ?? payload;
    };
    const sleep = (ms = 0) => new Promise((resolve) => window.setTimeout(resolve, ms));
    const buildOrderResultUrl = (orderId, params = {}) => {
        const search = new URLSearchParams({
            order_id: String(orderId || ''),
            ...params,
        });
        return `/mall/order-result?${search.toString()}`;
    };
    const buildWechatOrderResultUrl = (orderId, params = {}) => buildOrderResultUrl(orderId, {
        pay: 'wechat',
        ...params,
    });
    const buildWechatH5PayUrl = (payUrl, orderId) => {
        const redirectUrl = new URL(buildWechatOrderResultUrl(orderId, { wechat_return: '1' }), window.location.origin).toString();
        try {
            const url = new URL(payUrl);
            url.searchParams.set('redirect_url', redirectUrl);
            return url.toString();
        } catch (error) {
            const separator = String(payUrl || '').includes('?') ? '&' : '?';
            return `${payUrl}${separator}redirect_url=${encodeURIComponent(redirectUrl)}`;
        }
    };
    const confirmWechatPayStatus = async (orderId, options = {}) => {
        const attempts = Math.max(1, Number(options.attempts || 5));
        const delayMs = Math.max(200, Number(options.delayMs || 1200));
        let lastPayload = null;
        let lastError = null;

        for (let attempt = 0; attempt < attempts; attempt += 1) {
            try {
                lastPayload = await apiRequest(`/mall/api/orders/${orderId}/pay/wechat/status`);
                const order = lastPayload?.order || null;
                const tradeState = String(lastPayload?.trade_state || '').toUpperCase();
                if ((order?.payment_status || '') === 'paid' || (order?.status || '') === 'closed' || ['SUCCESS', 'CLOSED', 'REVOKED', 'PAYERROR'].includes(tradeState)) {
                    return lastPayload;
                }
                lastError = null;
            } catch (error) {
                lastError = error;
                if (attempt === attempts - 1) {
                    throw error;
                }
            }

            if (attempt < attempts - 1) {
                await sleep(delayMs);
            }
        }

        if (lastPayload) {
            return lastPayload;
        }
        if (lastError) {
            throw lastError;
        }

        return null;
    };
    const redirectToWechatOrderResult = (orderId, params = {}) => {
        window.location.href = buildWechatOrderResultUrl(orderId, params);
    };

    const flattenCategories = (items, bucket = [], depth = 0) => {
        (items || []).forEach((item, index) => {
            bucket.push({
                ...item,
                depth,
                sort_order: item.sort_order ?? index,
            });
            if (Array.isArray(item.children) && item.children.length > 0) {
                flattenCategories(item.children, bucket, depth + 1);
            }
        });
        return bucket;
    };

    const buildSkuOptions = (skus = []) => {
        const options = {};
        skus.forEach((sku) => {
            Object.entries(sku.attributes || {}).forEach(([name, value]) => {
                if (!options[name]) {
                    options[name] = [];
                }
                if (!options[name].includes(value)) {
                    options[name].push(value);
                }
            });
        });
        return options;
    };

    const editorUploadHandler = async (blobInfo) => {
        const uploadFile = await optimizeAdminUploadImage(blobInfo.blob(), blobInfo.filename());
        const formData = new FormData();
        formData.append('file', uploadFile.blob, uploadFile.filename);
        const response = await apiRequest('/mall/api/admin/upload', { method: 'POST', body: formData });
        return response.location;
    };

    const uploadAdminFile = async (file) => {
        const uploadFile = await optimizeAdminUploadImage(file, file.name);
        const formData = new FormData();
        formData.append('file', uploadFile.blob, uploadFile.filename);
        const response = await apiRequest('/mall/api/admin/upload', { method: 'POST', body: formData });
        return response.location;
    };

    const defaultHomeFilters = () => ({
        keyword: '',
        brand: '',
        category_id: '',
        sort: 'newest',
        page: 1,
        page_size: 8,
    });

    const defaultPager = (pageSize = 15) => ({
        page: 1,
        page_size: pageSize,
        total: 0,
        total_pages: 1,
        has_more: false,
    });

    const normalizePagerMeta = (meta = {}, fallbackPageSize = 15) => {
        const pageSize = Math.max(1, Number(meta.page_size || fallbackPageSize || 15));
        const total = Math.max(0, Number(meta.total || 0));
        const totalPages = Math.max(1, Number(meta.total_pages || Math.ceil(total / pageSize) || 1));

        return {
            page: Math.max(1, Number(meta.page || 1)),
            page_size: pageSize,
            total,
            total_pages: totalPages,
            has_more: Boolean(meta.has_more),
        };
    };

    const pagerNumbers = (meta = {}) => {
        const current = Math.max(1, Number(meta.page || 1));
        const totalPages = Math.max(1, Number(meta.total_pages || 1));
        const start = Math.max(1, current - 2);
        const end = Math.min(totalPages, start + 4);
        const normalizedStart = Math.max(1, end - 4);

        return Array.from({ length: end - normalizedStart + 1 }, (_, index) => normalizedStart + index);
    };

    const categoryOptionLabel = (item) => `${'· '.repeat(item.depth || 0)}${item.name}`;
    const defaultProductSkuForm = (item = {}) => ({
        id: item.id ?? null,
        label: item.label || Object.values(item.attributes || {}).join(' / ') || '',
        price: item.price ?? '',
        stock: item.stock ?? '',
        cover_image: item.cover_image || '',
    });

    const normalizeProductSkuForms = (skus = [], fallback = {}) => {
        if (!Array.isArray(skus) || skus.length === 0) {
            return [defaultProductSkuForm({
                label: fallback.is_course ? '课程版' : '默认规格',
                price: fallback.price ?? '',
                stock: fallback.stock_total ?? '',
                cover_image: fallback.cover_image || '',
            })];
        }

        return skus.map((sku) => defaultProductSkuForm({
            ...sku,
            label: Object.values(sku.attributes || {}).join(' / ') || sku.label || sku.sku_code || '',
        }));
    };

    const serializeProductSkus = (skus = [], fallbackCoverImage = '') => {
        return skus
            .map((sku, index) => ({
                sku_code: sku.id ? undefined : `SKU-${Date.now()}-${index + 1}`,
                price: Number(sku.price || 0),
                stock: Number(sku.stock || 0),
                cover_image: sku.cover_image || fallbackCoverImage,
                attributes: { 规格: (sku.label || '').trim() || `规格${index + 1}` },
            }))
            .filter((sku) => sku.stock >= 0);
    };

    const buildVisibleCategoryRows = (items, expandedIds = new Set(), bucket = [], depth = 0, parentVisible = true) => {
        (items || []).forEach((item) => {
            if (!parentVisible) {
                return;
            }

            const hasChildren = Array.isArray(item.children) && item.children.length > 0;
            bucket.push({
                ...item,
                depth,
                has_children: hasChildren,
                expanded: expandedIds.has(Number(item.id)),
            });

            if (hasChildren) {
                buildVisibleCategoryRows(item.children, expandedIds, bucket, depth + 1, expandedIds.has(Number(item.id)));
            }
        });

        return bucket;
    };

    const normalizeProductForm = (item = {}) => {
        const skus = normalizeProductSkuForms(item.skus || [], item);
        const totalStock = skus.reduce((total, sku) => total + Number(sku.stock || 0), 0) || Number(item.stock_total ?? 1);

        return {
            id: item.id ?? null,
            name: item.name || '',
            summary: item.summary || item.subtitle || '',
            subtitle: item.subtitle || '',
            brand: item.brand || '',
            category_id: item.category_id !== undefined && item.category_id !== null ? String(item.category_id) : '',
            price: skus[0]?.price ?? item.price ?? '',
            cover_image: item.cover_image || '',
            is_on_sale: Number(item.is_on_sale ?? 1) === 1,
            support_member_discount: Number(item.support_member_discount ?? 1) === 1,
            is_course: Number(item.is_course ?? 0) === 1,
            is_new_arrival: Number(item.is_new_arrival ?? 0) === 1,
            is_recommended_course: Number(item.is_recommended_course ?? 0) === 1,
            detail_html: item.detail_html || '',
            stock_total: totalStock,
            gallery_images: uniqueList((item.gallery || []).filter((image) => image && image !== item.cover_image)),
            skus,
        };
    };

    const normalizeActivityForm = (item = {}) => ({
        id: item.id ?? null,
        title: item.title || '',
        summary: item.summary || '',
        thumbnail_image: item.thumbnail_image || '',
        content_html: item.content_html || '',
        display_order: item.display_order ?? 0,
        is_active: Number(item.is_active ?? 1) === 1,
        starts_at: item.starts_at ? String(item.starts_at).replace(' ', 'T').slice(0, 16) : '',
        ends_at: item.ends_at ? String(item.ends_at).replace(' ', 'T').slice(0, 16) : '',
    });

    const normalizeToggleValue = (value, fallback = '1') => {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        if (value === true || value === 1 || value === '1') {
            return '1';
        }
        return '0';
    };

    const normalizeNotificationSettings = (notifications = {}) => ({
        admin_paid_enabled: normalizeToggleValue(notifications.admin_paid_enabled, '1'),
        admin_paid_template_id: notifications.admin_paid_template_id || '',
        admin_cancelled_enabled: normalizeToggleValue(notifications.admin_cancelled_enabled, '1'),
        admin_cancelled_template_id: notifications.admin_cancelled_template_id || '',
        user_paid_enabled: normalizeToggleValue(notifications.user_paid_enabled, '1'),
        user_paid_template_id: notifications.user_paid_template_id || '',
        user_shipped_enabled: normalizeToggleValue(notifications.user_shipped_enabled, '1'),
        user_shipped_template_id: notifications.user_shipped_template_id || '',
        user_cancelled_enabled: normalizeToggleValue(notifications.user_cancelled_enabled ?? notifications.user_closed_enabled, '1'),
        user_cancelled_template_id: notifications.user_cancelled_template_id || notifications.user_closed_template_id || '',
    });

    const normalizeSettings = (settings = {}) => ({
        membership_mysql: { host: '', port: '3306', database: '', username: '', password: '', charset: 'utf8mb4', ...(settings.membership_mysql || {}) },
        log: { min_level: 'info', retention_days: '30', max_size_mb: '10', ...(settings.log || {}) },
        wechat_pay: { app_id: '', merchant_id: '', merchant_serial_no: '', public_key_id: '', pay_mode: 'JSAPI', notify_url: '', api_v3_key: '', private_key_content: '', public_key_content: '', platform_cert_content: '', ...(settings.wechat_pay || {}) },
        wechat_service_account: { app_id: '', app_secret: '', ...(settings.wechat_service_account || {}) },
        notifications: normalizeNotificationSettings(settings.notifications || {}),
    });

    const defaultCategoryForm = () => ({ id: null, name: '', parent_id: '0', level: 1 });
    const defaultUserForm = () => ({ id: null, nickname: '', phone: '', password: '', membership_member_id: '', allow_duplicate_membership: false, status: 'active' });
    const defaultMemberForm = () => ({ id: null, fnumber: '', fname: '', fclassesid: '', fclassesname: '', initial_amount: 0, fbalance: 0, fmark: '', adjust_amount: '', adjust_mark: '' });
    const defaultShipOrderForm = () => ({ id: null, order_no: '', shipping_company: '顺丰速运', shipping_no: '' });
    const defaultCloseOrderForm = () => ({ id: null, order_no: '', reason: '管理后台关闭订单' });

    window.MallUtils = { formatMoney, notice, apiRequest, loadScriptOnce };

    const bindLogoutAction = () => {
        document.querySelectorAll('[data-logout]').forEach((button) => {
            if (button.dataset.bound === '1') {
                return;
            }

            button.dataset.bound = '1';
            button.addEventListener('click', async () => {
                if (button.disabled) {
                    return;
                }

                button.disabled = true;
                try {
                    await apiRequest('/mall/api/auth/logout', { method: 'POST', body: {} });
                    window.location.href = '/mall';
                } catch (error) {
                    notice(error.message, 'error');
                    button.disabled = false;
                }
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            syncHeaderOffset();
            bindBackNavigation();
            bindBackToTop();
            syncFloatingCartButton();
            bindLogoutAction();
            void refreshFloatingCartCount();
        }, { once: true });
    } else {
        syncHeaderOffset();
        bindBackNavigation();
        bindBackToTop();
        syncFloatingCartButton();
        bindLogoutAction();
        void refreshFloatingCartCount();
    }

    window.addEventListener('resize', syncHeaderOffset);
    window.addEventListener('scroll', syncBackToTopButton, { passive: true });
    window.addEventListener('resize', syncFloatingCartButton);

    document.addEventListener('alpine:init', () => {
        Alpine.data('loginPage', () => ({
            form: { phone: '', password: '' },
            wechat: { ...defaultWechatStatus(), loading: false },
            init() {
                void this.bootstrapWechat();
            },
            async bootstrapWechat() {
                await this.refreshWechatStatus();
                if (this.wechat.oauthOpenidReady) {
                    clearWechatOauthAttempted('login');
                    return;
                }
                if (!this.wechat.isWechatClient || hasWechatOauthAttempted('login')) {
                    return;
                }
                try {
                    await startWechatOauth('login');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async refreshWechatStatus() {
                try {
                    const status = await fetchWechatStatus();
                    this.wechat = { ...this.wechat, ...status };
                    if (status.oauthOpenidReady) {
                        clearWechatOauthAttempted('login');
                    }
                } catch (error) {
                    this.wechat = { ...this.wechat, isWechatClient: isWechatUserAgent() };
                }
            },
            async maybeBindCurrentWechatAfterLogin() {
                const status = await fetchWechatStatus();
                this.wechat = { ...this.wechat, ...status };
                if (status.oauthOpenidReady) {
                    clearWechatOauthAttempted('login');
                }
                if (!status.oauthOpenidReady || status.userHasOpenid) {
                    return;
                }
                if (status.oauthOpenidBoundToOtherUser && !status.currentWechatMatchesUser) {
                    notice('当前微信已绑定其他用户，当前账号无法重复绑定。', 'error');
                    return;
                }
                if (!window.confirm('是否绑定当前微信账号?')) {
                    return;
                }

                await apiRequest('/mall/api/wechat/bind', {
                    method: 'POST',
                    body: {},
                });
                notice('微信绑定成功。');
            },
            async submit() {
                try {
                    this.wechat.loading = true;
                    const data = await apiRequest('/mall/api/auth/login', {
                        method: 'POST',
                        body: this.form,
                    });
                    try {
                        await this.maybeBindCurrentWechatAfterLogin();
                    } catch (bindError) {
                        notice(bindError.message, 'error');
                    }
                    notice('登录成功。');
                    window.location.href = data.user.role === 'admin' ? '/mall/admin' : '/mall';
                } catch (error) {
                    notice(error.message, 'error');
                } finally {
                    this.wechat.loading = false;
                }
            },
            async loginWithWechat() {
                if (this.wechat.loading) {
                    return;
                }
                try {
                    this.wechat.loading = true;
                    await this.refreshWechatStatus();
                    if (!this.wechat.isWechatClient) {
                        throw new Error('请在微信客户端中打开。');
                    }
                    if (!this.wechat.oauthOpenidReady) {
                        await startWechatOauth('login');
                        return;
                    }

                    const data = await apiRequest('/mall/api/auth/wechat-login', {
                        method: 'POST',
                        body: {},
                    });
                    clearWechatOauthAttempted('login');
                    notice('登录成功。');
                    window.location.href = data.user.role === 'admin' ? '/mall/admin' : '/mall';
                } catch (error) {
                    notice(error.message, 'error');
                } finally {
                    this.wechat.loading = false;
                }
            },
        }));

        Alpine.data('mallHomePage', () => ({
            products: {
                data: [...(pageData.home?.featured_products || [])],
                meta: {
                    page: 1,
                    page_size: 8,
                    total: (pageData.home?.featured_products || []).length,
                    has_more: false,
                },
            },
            filterOptions: {
                brands: pageData.home?.brands || [],
                categories: pageData.home?.categories || [],
            },
            categoryOptions: flattenCategories(pageData.home?.categories || []),
            filters: defaultHomeFilters(),
            quickView: { open: false, data: null, quantity: 1, skuOptions: {}, selectedOptions: {}, currentSku: null },
            mobileFiltersOpen: window.innerWidth >= 1024,
            filterTimer: null,
            filterWatchSuspended: false,
            categoryLabel: categoryOptionLabel,
            inventoryLabel(item = {}) {
                return inventoryText(item.stock_total ?? 0, item.support_member_discount);
            },
            init() {
                ['keyword', 'brand', 'category_id', 'sort'].forEach((key) => {
                    this.$watch(`filters.${key}`, () => {
                        if (this.filterWatchSuspended) {
                            return;
                        }
                        this.scheduleFilterReload();
                    });
                });
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024) {
                        this.mobileFiltersOpen = true;
                    }
                });
                void this.reload(true);
            },
            scheduleFilterReload() {
                window.clearTimeout(this.filterTimer);
                this.filterTimer = window.setTimeout(() => {
                    void this.reload(true);
                }, 260);
            },
            async reload(reset = false) {
                if (reset) {
                    this.filters.page = 1;
                }
                try {
                    const payload = await apiRequest(`/mall/api/products?${new URLSearchParams(this.filters).toString()}`);
                    this.filterOptions = payload.filters || this.filterOptions;
                    this.categoryOptions = flattenCategories(payload.filters?.categories || this.filterOptions.categories || []);
                    this.products = payload;
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            resetFilters() {
                window.clearTimeout(this.filterTimer);
                this.filterWatchSuspended = true;
                this.filters = defaultHomeFilters();
                this.filterWatchSuspended = false;
                void this.reload(true);
            },
            async loadMore() {
                try {
                    const nextPage = Number(this.products.meta.page || 1) + 1;
                    const payload = await apiRequest(`/mall/api/products?${new URLSearchParams({ ...this.filters, page: nextPage }).toString()}`);
                    this.products.data = [...this.products.data, ...(payload.data || [])];
                    this.products.meta = payload.meta || this.products.meta;
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async openQuickView(id) {
                try {
                    this.quickView.data = await apiRequest(`/mall/api/products/${id}/quick-view`);
                    this.quickView.quantity = 1;
                    this.quickView.skuOptions = buildSkuOptions(this.quickView.data?.skus || []);
                    this.quickView.selectedOptions = {};
                    Object.entries(this.quickView.skuOptions).forEach(([name, values]) => {
                        this.quickView.selectedOptions[name] = values[0];
                    });
                    this.resolveQuickViewSku();
                    this.quickView.open = true;
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            selectQuickViewOption(name, value) {
                this.quickView.selectedOptions[name] = value;
                this.resolveQuickViewSku();
            },
            resolveQuickViewSku() {
                this.quickView.currentSku = (this.quickView.data?.skus || []).find((sku) => {
                    return Object.entries(this.quickView.selectedOptions).every(([name, value]) => (sku.attributes || {})[name] === value);
                }) || (this.quickView.data?.skus || [])[0] || null;
            },
            quickViewPrice() {
                return this.quickView.currentSku?.price ?? this.quickView.data?.price ?? 0;
            },
            quickViewStock() {
                return this.quickView.currentSku?.stock ?? this.quickView.data?.stock_total ?? 0;
            },
            quickViewLineTotal() {
                return this.quickViewPrice() * Math.max(1, Number(this.quickView.quantity) || 1);
            },
            quickViewShowsMemberPrice() {
                return hasMemberDiscount(this.quickView.data?.support_member_discount);
            },
            quickViewMemberTotal() {
                return this.quickViewShowsMemberPrice()
                    ? this.quickViewLineTotal() * memberDiscountRate()
                    : this.quickViewLineTotal();
            },
            buyNowFromQuickView() {
                if (!bootstrap.currentUser) {
                    window.location.href = '/mall/login';
                    return;
                }
                if (!this.quickView.currentSku || !this.quickView.data?.id) {
                    notice('请选择可购买规格。', 'error');
                    return;
                }
                const quantity = Math.max(1, Number(this.quickView.quantity) || 1);
                const stock = Math.max(0, Number(this.quickViewStock() || 0));
                window.location.href = buildCheckoutUrl('buy_now', {
                    product_id: this.quickView.data.id,
                    sku_id: this.quickView.currentSku.id,
                    quantity: stock > 0 ? Math.min(quantity, stock) : quantity,
                });
            },
            async addQuickViewToCart() {
                if (!bootstrap.currentUser) {
                    window.location.href = '/mall/login';
                    return;
                }
                if (!this.quickView.currentSku || !this.quickView.data?.id) {
                    notice('请选择可购买规格。', 'error');
                    return;
                }

                try {
                    await apiRequest('/mall/api/cart', {
                        method: 'POST',
                        body: {
                            product_id: this.quickView.data.id,
                            sku_id: this.quickView.currentSku.id,
                            quantity: Number(this.quickView.quantity) || 1,
                        },
                    });
                    this.quickView.open = false;
                    await refreshFloatingCartCount();
                    pulseFloatingCartBadge();
                    if (window.innerWidth > 640) {
                        notice('已加入购物车。');
                    }
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            formatMoney,
        }));

        Alpine.data('productDetailPage', () => ({
            product: pageData.product || {},
            gallery: [],
            activeImage: '',
            galleryDragOffset: 0,
            detailImages: [],
            quantity: 1,
            skuOptions: {},
            selectedOptions: {},
            currentSku: null,
            viewer: { open: false, images: [], index: 0 },
            viewerDragOffset: 0,
            swipe: { target: '', startX: 0, startY: 0, deltaX: 0, horizontal: false, locked: false },
            tapSuppressUntil: 0,
            share: { ready: false, supported: false, loading: false, configuredUrl: '', readyPromise: null },
            init() {
                this.gallery = uniqueList([this.product.cover_image, ...(this.product.gallery || [])]);
                this.activeImage = this.gallery[0] || '';
                this.skuOptions = buildSkuOptions(this.product.skus || []);
                Object.entries(this.skuOptions).forEach(([name, values]) => {
                    this.selectedOptions[name] = values[0];
                });
                this.resolveSku();
                this.$nextTick(() => {
                    this.syncDetailImages();
                    if (this.isWechatClient()) {
                        void this.ensureWechatShareReady().catch(() => {});
                    }
                });
            },
            galleryImages() {
                return uniqueList([...(this.gallery || []), this.activeImage]);
            },
            selectGalleryImage(image) {
                this.activeImage = image;
                this.galleryDragOffset = 0;
            },
            activeGalleryIndex() {
                const images = this.galleryImages();
                const index = images.indexOf(this.activeImage);
                return index >= 0 ? index : 0;
            },
            galleryTrackStyle() {
                return this.trackTranslateStyle(this.activeGalleryIndex(), this.galleryDragOffset, this.swipe.target === 'gallery');
            },
            shiftGallery(step) {
                const images = this.galleryImages();
                if (images.length < 2) {
                    return;
                }

                const nextIndex = (this.activeGalleryIndex() + step + images.length) % images.length;
                this.activeImage = images[nextIndex];
                this.galleryDragOffset = 0;
            },
            openGalleryViewer() {
                if (this.consumeTapSuppression()) {
                    return;
                }
                this.openViewer(this.galleryImages(), this.activeGalleryIndex());
            },
            openViewer(images = [], index = 0) {
                const normalized = uniqueList(images);
                if (!normalized.length) {
                    return;
                }

                this.viewer.images = normalized;
                this.viewer.index = Math.max(0, Math.min(index, normalized.length - 1));
                this.viewerDragOffset = 0;
                this.viewer.open = true;
                document.body.style.overflow = 'hidden';
            },
            closeViewer() {
                this.viewer.open = false;
                this.viewerDragOffset = 0;
                document.body.style.overflow = '';
            },
            viewerTrackStyle() {
                return this.trackTranslateStyle(this.viewer.index, this.viewerDragOffset, this.swipe.target === 'viewer');
            },
            viewerPrev() {
                if (this.viewer.images.length < 2) {
                    return;
                }
                this.viewer.index = (this.viewer.index - 1 + this.viewer.images.length) % this.viewer.images.length;
                this.viewerDragOffset = 0;
            },
            viewerNext() {
                if (this.viewer.images.length < 2) {
                    return;
                }
                this.viewer.index = (this.viewer.index + 1) % this.viewer.images.length;
                this.viewerDragOffset = 0;
            },
            startSwipe(target, event) {
                this.swipe = {
                    target,
                    startX: touchPointX(event),
                    startY: touchPointY(event),
                    deltaX: 0,
                    horizontal: false,
                    locked: false,
                };
            },
            moveSwipe(target, event) {
                if (this.swipe.target !== target) {
                    return;
                }

                const deltaX = touchPointX(event) - this.swipe.startX;
                const deltaY = touchPointY(event) - this.swipe.startY;
                if (!this.swipe.locked) {
                    if (Math.max(Math.abs(deltaX), Math.abs(deltaY)) < 8) {
                        return;
                    }
                    this.swipe.locked = true;
                    this.swipe.horizontal = Math.abs(deltaX) > Math.abs(deltaY);
                }

                if (!this.swipe.horizontal) {
                    return;
                }

                if (event.cancelable) {
                    event.preventDefault();
                }

                this.swipe.deltaX = deltaX;
                this.setSwipeOffset(target, this.limitSwipeOffset(target, deltaX));
            },
            endSwipe(target, event) {
                if (this.swipe.target !== target) {
                    return;
                }

                const deltaX = this.swipe.deltaX || (touchPointX(event) - this.swipe.startX);
                const deltaY = touchPointY(event) - this.swipe.startY;
                const threshold = Math.min(140, window.innerWidth * 0.18);
                const shouldSwitch = this.swipe.horizontal
                    && Math.abs(deltaX) > Math.abs(deltaY)
                    && Math.abs(deltaX) >= threshold;

                if (shouldSwitch) {
                    const step = deltaX < 0 ? 1 : -1;
                    if (target === 'gallery') {
                        this.shiftGallery(step);
                    } else if (step > 0) {
                        this.viewerNext();
                    } else {
                        this.viewerPrev();
                    }
                    this.tapSuppressUntil = Date.now() + 260;
                }

                this.swipe = { target: '', startX: 0, startY: 0, deltaX: 0, horizontal: false, locked: false };
                this.setSwipeOffset(target, 0);
            },
            cancelSwipe(target) {
                if (this.swipe.target === target) {
                    this.swipe = { target: '', startX: 0, startY: 0, deltaX: 0, horizontal: false, locked: false };
                }
                this.setSwipeOffset(target, 0);
            },
            setSwipeOffset(target, value) {
                if (target === 'gallery') {
                    this.galleryDragOffset = value;
                    return;
                }
                this.viewerDragOffset = value;
            },
            limitSwipeOffset(target, deltaX) {
                const images = target === 'gallery' ? this.galleryImages() : this.viewer.images;
                const currentIndex = target === 'gallery' ? this.activeGalleryIndex() : this.viewer.index;
                if (images.length <= 1) {
                    return deltaX / 4;
                }
                if ((currentIndex === 0 && deltaX > 0) || (currentIndex === images.length - 1 && deltaX < 0)) {
                    return deltaX / 3;
                }
                return deltaX;
            },
            trackTranslateStyle(index, offset, isDragging = false) {
                const translate = `translate3d(calc(${index * -100}% + ${offset}px), 0, 0)`;
                const transition = isDragging ? 'none' : 'transform 320ms cubic-bezier(0.22, 1, 0.36, 1)';
                return `transform: ${translate}; transition: ${transition};`;
            },
            consumeTapSuppression() {
                return Date.now() < this.tapSuppressUntil;
            },
            syncDetailImages() {
                const article = this.$root.querySelector('[data-product-detail-body]');
                if (!article) {
                    return;
                }

                this.detailImages = uniqueList(Array.from(article.querySelectorAll('img')).map((image) => image.currentSrc || image.getAttribute('src') || ''));
                article.querySelectorAll('img').forEach((image) => {
                    image.classList.add('product-detail-zoomable');
                    image.dataset.detailImage = '1';
                });

                if (article.dataset.viewerBound === '1') {
                    return;
                }

                article.dataset.viewerBound = '1';
                article.addEventListener('click', (event) => {
                    const image = event.target.closest('img[data-detail-image]');
                    if (!image) {
                        return;
                    }

                    const src = image.currentSrc || image.getAttribute('src') || '';
                    const images = this.detailImages.length ? this.detailImages : [src];
                    const index = Math.max(0, images.indexOf(src));
                    this.openViewer(images, index);
                });
            },
            selectOption(name, value) {
                this.selectedOptions[name] = value;
                this.resolveSku();
            },
            resolveSku() {
                this.currentSku = (this.product.skus || []).find((sku) => {
                    return Object.entries(this.selectedOptions).every(([name, value]) => (sku.attributes || {})[name] === value);
                }) || (this.product.skus || [])[0] || null;
                if (this.currentSku?.cover_image) {
                    this.activeImage = this.currentSku.cover_image;
                }
            },
            get currentPrice() {
                return this.currentSku?.price ?? this.product.price ?? 0;
            },
            get currentStock() {
                return this.currentSku?.stock ?? this.product.stock_total ?? 0;
            },
            currentInventoryLabel() {
                return inventoryText(this.currentStock, this.product.support_member_discount);
            },
            lineTotal() {
                return this.currentPrice * Math.max(1, Number(this.quantity) || 1);
            },
            showsMemberPrice() {
                return hasMemberDiscount(this.product.support_member_discount);
            },
            memberLineTotal() {
                return this.showsMemberPrice()
                    ? this.lineTotal() * memberDiscountRate()
                    : this.lineTotal();
            },
            isWechatClient() {
                return /MicroMessenger/i.test(window.navigator.userAgent || '');
            },
            buildWechatShareData() {
                const link = window.location.href.split('#')[0];
                const imgUrl = absoluteUrl(this.currentSku?.cover_image || this.product.cover_image || this.activeImage || this.galleryImages()[0] || '');
                return {
                    title: this.product.name || document.title,
                    desc: this.product.summary || '',
                    link,
                    imgUrl,
                };
            },
            async applyWechatShareData() {
                const shareData = this.buildWechatShareData();
                let applied = false;
                ['updateAppMessageShareData', 'updateTimelineShareData', 'onMenuShareAppMessage', 'onMenuShareTimeline'].forEach((method) => {
                    if (typeof window.wx?.[method] === 'function') {
                        window.wx[method](shareData);
                        applied = true;
                    }
                });

                if (typeof window.wx?.showOptionMenu === 'function') {
                    window.wx.showOptionMenu();
                }

                if (!applied) {
                    throw new Error('当前微信版本暂不支持网页分享接口。');
                }
            },
            async ensureWechatShareReady() {
                if (!this.isWechatClient()) {
                    throw new Error('请在微信客户端中使用分享功能。');
                }

                const currentUrl = window.location.href.split('#')[0];
                if (this.share.ready && this.share.configuredUrl === currentUrl && window.wx) {
                    return true;
                }

                if (this.share.readyPromise) {
                    return this.share.readyPromise;
                }

                this.share.loading = true;
                this.share.readyPromise = (async () => {
                    await loadScriptOnce(wechatJssdkScriptSrc);
                    if (!window.wx) {
                        throw new Error('微信分享组件加载失败。');
                    }

                    const config = await apiRequest(`/mall/api/wechat/jssdk-config?${new URLSearchParams({ url: currentUrl }).toString()}`);
                    await new Promise((resolve, reject) => {
                        let settled = false;
                        const finish = (callback) => {
                            if (settled) {
                                return;
                            }
                            settled = true;
                            callback();
                        };

                        window.wx.config({
                            debug: false,
                            appId: config.appId,
                            timestamp: config.timestamp,
                            nonceStr: config.nonceStr,
                            signature: config.signature,
                            jsApiList: config.jsApiList || ['updateAppMessageShareData', 'updateTimelineShareData', 'onMenuShareAppMessage', 'onMenuShareTimeline', 'showOptionMenu'],
                        });
                        window.wx.ready(() => finish(resolve));
                        window.wx.error(() => finish(() => reject(new Error('微信分享配置失败，请确认当前域名已配置到 JS 接口安全域名。'))));
                    });

                    this.share.ready = true;
                    this.share.supported = true;
                    this.share.configuredUrl = currentUrl;
                    await this.applyWechatShareData();
                    return true;
                })().catch((error) => {
                    this.share.ready = false;
                    this.share.supported = false;
                    throw error;
                }).finally(() => {
                    this.share.loading = false;
                    this.share.readyPromise = null;
                });

                return this.share.readyPromise;
            },
            async shareProduct() {
                try {
                    await this.ensureWechatShareReady();
                    await this.applyWechatShareData();
                    const shareData = this.buildWechatShareData();
                    try {
                        await invokeWechatShareBridge(shareData);
                        notice('微信分享卡片已准备好。');
                        return;
                    } catch (bridgeError) {
                        if (bridgeError.message === '已取消分享。') {
                            notice(bridgeError.message, 'error');
                            return;
                        }
                    }
                    notice('微信分享卡片已准备好，请点击右上角发送给微信好友。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            buyNow() {
                if (!bootstrap.currentUser) {
                    window.location.href = '/mall/login';
                    return;
                }
                if (!this.currentSku) {
                    notice('请选择有效的 SKU。', 'error');
                    return;
                }
                const quantity = Math.max(1, Number(this.quantity) || 1);
                const stock = Math.max(0, Number(this.currentStock || 0));
                window.location.href = buildCheckoutUrl('buy_now', {
                    product_id: this.product.id,
                    sku_id: this.currentSku.id,
                    quantity: stock > 0 ? Math.min(quantity, stock) : quantity,
                });
            },
            async addToCart() {
                if (!bootstrap.currentUser) {
                    window.location.href = '/mall/login';
                    return;
                }
                if (!this.currentSku) {
                    notice('请选择有效的 SKU。', 'error');
                    return;
                }
                try {
                    await apiRequest('/mall/api/cart', {
                        method: 'POST',
                        body: {
                            product_id: this.product.id,
                            sku_id: this.currentSku.id,
                            quantity: Number(this.quantity) || 1,
                        },
                    });
                    notice('已加入购物车。');
                    await refreshFloatingCartCount();
                    pulseFloatingCartBadge();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            formatMoney,
        }));

        Alpine.data('profilePage', () => ({
            tabs: [
                { key: 'profile', label: '个人信息' },
                { key: 'addresses', label: '收货地址' },
                { key: 'orders', label: '历史订单' },
                { key: 'wallet', label: '我的钱包' },
            ],
            activeTab: queryParam('tab') || 'profile',
            profile: { nickname: '', phone: '' },
            password: { current_password: '', new_password: '' },
            addresses: [],
            addressModalOpen: false,
            addressForm: { id: null, receiver_name: '', receiver_phone: '', province: '', city: '', district: '', detail_address: '', is_default: true },
            orders: [],
            orderGroups: [
                { key: 'all', label: '全部' },
                { key: 'pending_payment', label: '待付款' },
                { key: 'paid', label: '已付款' },
                { key: 'shipped', label: '已发货' },
                { key: 'closed', label: '已关闭' },
            ],
            orderGroup: 'all',
            orderPager: defaultPager(15),
            orderPageSizeOptions: [15, 30, 50, 100],
            wallet: { member: pageData.member || null, records: [] },
            regions: [],
            cities: [],
            districts: [],
            init() {
                if (!this.tabs.find((tab) => tab.key === this.activeTab)) {
                    this.activeTab = 'profile';
                }
                void this.bootstrapPage();
            },
            emptyAddressForm() {
                return {
                    id: null,
                    receiver_name: '',
                    receiver_phone: this.profile.phone || '',
                    province: '',
                    city: '',
                    district: '',
                    detail_address: '',
                    is_default: true,
                };
            },
            orderPages() {
                return pagerNumbers(this.orderPager);
            },
            changeOrderPage(page) {
                const nextPage = Math.max(1, Math.min(Number(this.orderPager.total_pages || 1), Number(page || 1)));
                if (nextPage === Number(this.orderPager.page || 1)) {
                    return;
                }
                this.orderPager = { ...this.orderPager, page: nextPage };
                void this.loadOrders();
            },
            changeOrderPageSize(size) {
                this.orderPager = { ...this.orderPager, page: 1, page_size: Number(size || 15) };
                void this.loadOrders();
            },
            switchOrderGroup(group) {
                this.orderGroup = group;
                this.orderPager = { ...this.orderPager, page: 1 };
                void this.loadOrders();
            },
            orderStatusLabel,
            openAddressModal(item = null) {
                this.addressForm = item
                    ? { ...item, is_default: Number(item.is_default) === 1 }
                    : this.emptyAddressForm();
                this.syncCities();
                this.syncDistricts();
                this.addressModalOpen = true;
            },
            closeAddressModal() {
                this.addressModalOpen = false;
                this.addressForm = this.emptyAddressForm();
                this.cities = [];
                this.districts = [];
            },
            async bootstrapPage() {
                await Promise.all([this.loadProfile(), this.loadAddresses(), this.loadOrders(), this.loadWallet(), this.loadRegions()]);
            },
            async loadProfile() {
                try {
                    const data = await apiRequest('/mall/api/profile');
                    this.profile.nickname = data.user?.nickname || '';
                    this.profile.phone = data.user?.phone || '';
                    this.wallet.member = data.member;
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async saveProfile() {
                try {
                    await apiRequest('/mall/api/profile', { method: 'PUT', body: this.profile });
                    notice('个人信息已保存。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async changePassword() {
                try {
                    await apiRequest('/mall/api/profile/password', { method: 'PUT', body: this.password });
                    this.password = { current_password: '', new_password: '' };
                    notice('密码修改成功。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadAddresses() {
                try {
                    this.addresses = await apiRequest('/mall/api/addresses');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            editAddress(item) {
                this.openAddressModal(item);
            },
            async saveAddress() {
                try {
                    const url = this.addressForm.id ? `/mall/api/addresses/${this.addressForm.id}` : '/mall/api/addresses';
                    const method = this.addressForm.id ? 'PUT' : 'POST';
                    await apiRequest(url, { method, body: this.addressForm });
                    notice('地址已保存。');
                    this.closeAddressModal();
                    await this.loadAddresses();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async removeAddress(id) {
                try {
                    await apiRequest(`/mall/api/addresses/${id}`, { method: 'DELETE', body: {} });
                    notice('地址已删除。');
                    await this.loadAddresses();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadOrders() {
                try {
                    const query = new URLSearchParams({
                        group: this.orderGroup,
                        page: this.orderPager.page,
                        page_size: this.orderPager.page_size,
                    }).toString();
                    const payload = await apiRequest(`/mall/api/orders?${query}`);
                    this.orders = payload.items || [];
                    this.orderPager = normalizePagerMeta(payload.meta, this.orderPager.page_size);
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            openOrder(id) {
                window.location.href = `/mall/order-detail?order_id=${id}`;
            },
            payOrder(id) {
                window.location.href = buildCheckoutUrl('repay', { order_id: id });
            },
            async cancelOrder(id) {
                if (!window.confirm('确认取消这个订单吗？')) {
                    return;
                }
                try {
                    await apiRequest(`/mall/api/orders/${id}/cancel`, { method: 'POST', body: {} });
                    notice('订单已取消。');
                    await this.loadOrders();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async completeOrder(id) {
                try {
                    await apiRequest(`/mall/api/orders/${id}/complete`, { method: 'POST', body: {} });
                    notice('已确认收货。');
                    await this.loadOrders();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadWallet() {
                try {
                    this.wallet = await apiRequest('/mall/api/wallet');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadRegions() {
                try {
                    const response = await fetch('/assets/data/china_regions.json');
                    this.regions = await response.json();
                } catch (error) {
                    console.warn('省市区数据加载失败', error);
                }
            },
            syncCities() {
                const province = this.regions.find((item) => item.name === this.addressForm.province);
                this.cities = province ? province.cities : [];
                if (!this.cities.find((city) => city.name === this.addressForm.city)) {
                    this.addressForm.city = '';
                }
                this.syncDistricts();
            },
            syncDistricts() {
                const city = this.cities.find((item) => item.name === this.addressForm.city);
                this.districts = city ? city.districts : [];
                if (!this.districts.includes(this.addressForm.district)) {
                    this.addressForm.district = '';
                }
            },
            formatMoney,
        }));

        Alpine.data('cartPage', () => ({
            cart: { items: [], summary: {} },
            selectedIds: [],
            init() {
                void this.loadCart();
            },
            allSelected() {
                return this.cart.items.length > 0 && this.selectedIds.length === this.cart.items.length;
            },
            selectedValidIds() {
                return this.cart.items
                    .filter((item) => this.selectedIds.includes(item.id) && item.item_status === 'valid')
                    .map((item) => item.id);
            },
            selectedSummary() {
                return this.cart.items.reduce((summary, item) => {
                    if (!this.selectedIds.includes(item.id) || item.item_status !== 'valid') {
                        return summary;
                    }

                    summary.subtotal += Number(item.unit_price || 0) * Number(item.quantity || 0);
                    summary.payable += Number(item.final_price || 0) * Number(item.quantity || 0);
                    summary.discount = summary.subtotal - summary.payable;
                    return summary;
                }, { subtotal: 0, discount: 0, payable: 0 });
            },
            toggleAll(checked) {
                this.selectedIds = checked ? this.cart.items.map((item) => item.id) : [];
            },
            toggleSelected(id, checked) {
                const normalized = Number(id || 0);
                if (checked) {
                    this.selectedIds = uniqueList([...this.selectedIds, normalized]);
                    return;
                }
                this.selectedIds = this.selectedIds.filter((itemId) => Number(itemId) !== normalized);
            },
            async loadCart() {
                try {
                    this.cart = await apiRequest('/mall/api/cart');
                    this.selectedIds = this.cart.items.map((item) => item.id);
                    await refreshFloatingCartCount(cartItemCount(this.cart));
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async changeQty(item, quantity) {
                try {
                    this.cart = await apiRequest(`/mall/api/cart/${item.id}`, { method: 'PUT', body: { quantity } });
                    this.selectedIds = this.selectedIds.filter((itemId) => this.cart.items.some((cartItem) => Number(cartItem.id) === Number(itemId)));
                    if (!this.selectedIds.length) {
                        this.selectedIds = this.cart.items.map((cartItem) => cartItem.id);
                    }
                    await refreshFloatingCartCount(cartItemCount(this.cart));
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async remove(id) {
                try {
                    await apiRequest(`/mall/api/cart/${id}`, { method: 'DELETE', body: {} });
                    await this.loadCart();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            checkoutSelected() {
                const selectedIds = this.selectedValidIds();
                if (!selectedIds.length) {
                    notice('请至少勾选一件可结算商品。', 'error');
                    return;
                }
                window.location.href = buildCheckoutUrl('cart', { items: selectedIds.join(',') });
            },
            formatMoney,
        }));

        Alpine.data('checkoutPage', () => ({
            checkout: pageData.checkout || { mode: 'cart', items: [], summary: {} },
            addresses: pageData.addresses || [],
            selectedAddressId: pageData.addresses?.[0]?.id || null,
            init() {
                if (this.checkout.mode === 'repay') {
                    this.selectedAddressId = null;
                    return;
                }
                const defaultAddress = this.addresses.find((item) => Number(item.is_default) === 1);
                this.selectedAddressId = defaultAddress?.id || this.addresses?.[0]?.id || null;
            },
            checkoutTitle() {
                if (this.checkout.mode === 'buy_now') {
                    return '立即购买确认页';
                }
                if (this.checkout.mode === 'repay') {
                    return '继续支付待付款订单';
                }
                return '购物车确认页';
            },
            checkoutItemSpec(item = {}) {
                return item.sku_name || Object.values(item.attributes || {}).join(' / ') || '默认规格';
            },
            buyNowItem() {
                return this.checkout.items?.[0] || null;
            },
            buyNowQuantity() {
                return Math.max(1, Number(this.buyNowItem()?.quantity || 1));
            },
            updateBuyNowQuantity(quantity) {
                const item = this.buyNowItem();
                if (!item) {
                    return;
                }
                const nextValue = Math.max(1, Number(quantity || 1));
                const stock = Number(item.sku_stock ?? item.stock_total ?? 0);
                item.quantity = stock > 0 ? Math.min(nextValue, stock) : nextValue;
            },
            displaySummary() {
                if (this.checkout.mode !== 'buy_now') {
                    return this.checkout.summary || { subtotal: 0, discount: 0, payable: 0 };
                }
                const item = this.buyNowItem();
                if (!item) {
                    return { subtotal: 0, discount: 0, payable: 0 };
                }
                const quantity = this.buyNowQuantity();
                const subtotal = Number(item.unit_price || 0) * quantity;
                const payable = Number(item.final_price || 0) * quantity;
                return {
                    subtotal,
                    discount: subtotal - payable,
                    payable,
                };
            },
            async createOrder(paymentMethod) {
                if (this.checkout.mode !== 'repay' && !this.selectedAddressId) {
                    notice('请选择收货地址。', 'error');
                    return;
                }
                try {
                    let order = null;
                    if (this.checkout.mode === 'repay') {
                        order = { id: this.checkout.order_id };
                    } else if (this.checkout.mode === 'buy_now') {
                        const item = this.buyNowItem();
                        if (!item) {
                            notice('商品信息异常，请返回重试。', 'error');
                            return;
                        }
                        order = await apiRequest('/mall/api/orders', {
                            method: 'POST',
                            body: {
                                mode: 'buy_now',
                                address_id: this.selectedAddressId,
                                product_id: item.product_id,
                                sku_id: item.sku_id,
                                quantity: this.buyNowQuantity(),
                            },
                        });
                    } else {
                        order = await apiRequest('/mall/api/orders', {
                            method: 'POST',
                            body: {
                                mode: 'cart',
                                address_id: this.selectedAddressId,
                                selected_item_ids: this.checkout.selected_item_ids || [],
                            },
                        });
                    }

                    if (this.checkout.mode === 'cart') {
                        await refreshFloatingCartCount();
                    }

                    if (paymentMethod === 'balance') {
                        await apiRequest(`/mall/api/orders/${order.id}/pay/balance`, { method: 'POST', body: {} });
                        await refreshFloatingCartCount();
                        window.location.href = `/mall/order-result?order_id=${order.id}`;
                        return;
                    }

                    const payResult = await apiRequest(`/mall/api/orders/${order.id}/pay/wechat`, { method: 'POST', body: {} });
                    if (payResult.mode === 'H5' && payResult.pay_url) {
                        window.location.href = buildWechatH5PayUrl(payResult.pay_url, order.id);
                        return;
                    }

                    if (payResult.pay_params) {
                        const bridge = window.WeixinJSBridge || await waitForWeixinBridge();
                        if (bridge && typeof bridge.invoke === 'function') {
                            bridge.invoke('getBrandWCPayRequest', payResult.pay_params, async (bridgeResult = {}) => {
                                try {
                                    const confirmation = await confirmWechatPayStatus(order.id);
                                    const confirmedOrder = confirmation?.order || null;
                                    if ((confirmedOrder?.payment_status || '') === 'paid') {
                                        notice('支付结果已确认。');
                                    } else if (/cancel/i.test(String(bridgeResult.err_msg || ''))) {
                                        notice('已取消微信支付，可稍后在订单列表继续支付。', 'error');
                                    } else if (confirmation?.trade_state_desc) {
                                        notice(confirmation.trade_state_desc, 'error');
                                    }
                                } catch (error) {
                                    notice(error.message || '支付结果确认失败，请稍后在订单中查看。', 'error');
                                }

                                redirectToWechatOrderResult(order.id, { wechat_checked: '1' });
                            });
                            return;
                        }
                    }

                    notice(payResult.message || '微信支付参数已生成，请在微信环境中完成支付。');
                    redirectToWechatOrderResult(order.id, { wechat_checked: '1' });
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            formatMoney,
        }));

        Alpine.data('bindWechatPage', () => ({
            status: { ...defaultWechatStatus() },
            loading: false,
            init() {
                void this.bootstrapWechat();
            },
            async bootstrapWechat() {
                await this.refreshStatus();
                if (this.status.oauthOpenidReady) {
                    clearWechatOauthAttempted('bind');
                    return;
                }
                if (!this.status.isWechatClient || hasWechatOauthAttempted('bind')) {
                    return;
                }
                try {
                    await startWechatOauth('bind');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async refreshStatus() {
                try {
                    const status = await fetchWechatStatus();
                    this.status = { ...this.status, ...status };
                    if (status.oauthOpenidReady) {
                        clearWechatOauthAttempted('bind');
                    }
                } catch (error) {
                    this.status = { ...this.status, isWechatClient: isWechatUserAgent() };
                }
            },
            async retryWechatOauth() {
                try {
                    this.loading = true;
                    if (!this.status.isWechatClient) {
                        throw new Error('请在微信客户端中打开。');
                    }
                    await startWechatOauth('bind');
                } catch (error) {
                    notice(error.message, 'error');
                } finally {
                    this.loading = false;
                }
            },
            async bindCurrentWechat() {
                try {
                    this.loading = true;
                    await this.refreshStatus();
                    if (!this.status.isWechatClient) {
                        throw new Error('请在微信客户端中打开。');
                    }
                    if (!this.status.oauthOpenidReady) {
                        await startWechatOauth('bind');
                        return;
                    }
                    if (this.status.oauthOpenidBoundToOtherUser && !this.status.currentWechatMatchesUser) {
                        throw new Error('当前微信已绑定其他账号，无法重复绑定。');
                    }
                    if (!window.confirm('是否绑定当前微信账号?')) {
                        return;
                    }

                    await apiRequest('/mall/api/wechat/bind', {
                        method: 'POST',
                        body: {},
                    });
                    notice('微信绑定成功。');
                    await this.refreshStatus();
                } catch (error) {
                    notice(error.message, 'error');
                } finally {
                    this.loading = false;
                }
            },
            async unbindCurrentWechat() {
                if (!window.confirm('确认解除当前微信绑定吗？')) {
                    return;
                }
                try {
                    this.loading = true;
                    await apiRequest('/mall/api/wechat/unbind', {
                        method: 'POST',
                        body: {},
                    });
                    notice('微信解绑成功。');
                    await this.refreshStatus();
                } catch (error) {
                    notice(error.message, 'error');
                } finally {
                    this.loading = false;
                }
            },
        }));

        Alpine.data('orderResultPage', () => ({
            order: null,
            wechatChecking: false,
            wechatCheckMessage: '',
            init() {
                void this.loadOrder();
            },
            async loadOrder() {
                const orderId = queryParam('order_id');
                if (!orderId) {
                    return;
                }
                try {
                    this.order = await apiRequest(`/mall/api/orders/${orderId}`);
                    if (queryParam('pay') === 'wechat' && this.order && this.order.payment_status !== 'paid' && this.order.status === 'pending_payment') {
                        await this.confirmWechatOrder(orderId);
                    }
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            resultTitle() {
                if (this.wechatChecking) {
                    return '正在确认支付结果';
                }
                return orderResultTitle(this.order);
            },
            async confirmWechatOrder(orderId) {
                this.wechatChecking = true;
                this.wechatCheckMessage = '正在向微信确认支付结果，请稍候。';
                try {
                    const payload = await confirmWechatPayStatus(orderId);
                    this.order = payload?.order || this.order;
                    if ((this.order?.payment_status || '') === 'paid') {
                        this.wechatCheckMessage = '支付结果已和微信确认。';
                    } else if ((this.order?.status || '') === 'closed' || String(payload?.trade_state || '').toUpperCase() === 'CLOSED') {
                        this.wechatCheckMessage = '该微信订单已关闭。';
                    } else {
                        this.wechatCheckMessage = payload?.trade_state_desc || '支付结果暂未完成，可稍后在订单列表继续查看。';
                    }
                } catch (error) {
                    this.wechatCheckMessage = error.message || '微信支付结果确认失败。';
                    notice(this.wechatCheckMessage, 'error');
                } finally {
                    this.wechatChecking = false;
                }
            },
            statusLabel: orderStatusLabel,
            paymentMethodLabel,
            orderReceiverAddress,
            orderItemSpecLabel,
            orderClosedReasonLabel,
            orderHasShippingInfo,
            orderHasCloseInfo,
            copyText,
            formatMoney,
        }));

        Alpine.data('orderDetailPage', () => ({
            order: null,
            loading: true,
            errorMessage: '',
            init() {
                void this.loadOrder();
            },
            requestUrl() {
                const token = queryParam('token');
                if (token) {
                    return `/mall/api/order-detail-access?token=${encodeURIComponent(token)}`;
                }

                const orderId = queryParam('order_id');
                if (orderId) {
                    return `/mall/api/orders/${orderId}`;
                }

                return '';
            },
            async loadOrder() {
                const url = this.requestUrl();
                if (!url) {
                    this.errorMessage = '缺少订单参数。';
                    this.loading = false;
                    return;
                }

                try {
                    this.order = await apiRequest(url);
                } catch (error) {
                    this.errorMessage = error.message || '读取订单失败。';
                } finally {
                    this.loading = false;
                }
            },
            statusLabel: orderStatusLabel,
            paymentMethodLabel,
            orderReceiverAddress,
            orderItemSpecLabel,
            orderClosedReasonLabel,
            orderHasShippingInfo,
            orderHasCloseInfo,
            copyText,
            formatMoney,
        }));

        Alpine.data('adminProductEditorPage', () => ({
            categories: flattenCategories(pageData.categories || []),
            productForm: normalizeProductForm(pageData.product || {}),
            detailMode: 'preview',
            detailViewportMode: 'ratio',
            uploading: { cover: false, gallery: false },
            init() {
                const syncCategorySelection = (value) => {
                    const normalized = value !== undefined && value !== null && value !== '' ? String(value) : '';
                    this.productForm.category_id = normalized;
                    this.$nextTick(() => {
                        window.requestAnimationFrame(() => {
                            if (this.$refs.categorySelect) {
                                this.$refs.categorySelect.value = normalized;
                            }
                        });
                    });
                };

                const existingCategoryId = pageData.product?.category_id;
                if (existingCategoryId !== undefined && existingCategoryId !== null && existingCategoryId !== '') {
                    syncCategorySelection(existingCategoryId);
                    return;
                }

                if (!this.productForm.category_id && this.categories[0]) {
                    syncCategorySelection(this.categories[0].id);
                }

                this.handleEditorResize = () => {
                    if (this.detailMode === 'edit') {
                        this.refreshDetailEditorViewport();
                    }
                };
                window.addEventListener('resize', this.handleEditorResize);
            },
            categoryLabel: categoryOptionLabel,
            refreshDetailEditorViewport() {
                const editor = window.tinymce?.get('product-detail-editor-page');
                applyRichTextEditorHeight(editor, this.$refs.detailEditorHost, this.detailViewportMode);
            },
            toggleDetailViewportMode() {
                this.detailViewportMode = this.detailViewportMode === 'ratio' ? 'fluid' : 'ratio';
                this.$nextTick(() => this.refreshDetailEditorViewport());
            },
            addSkuRow() {
                this.productForm.skus = [
                    ...(this.productForm.skus || []),
                    defaultProductSkuForm({
                        label: `规格${(this.productForm.skus || []).length + 1}`,
                        price: this.productForm.price || '',
                        stock: this.productForm.stock_total || '',
                        cover_image: this.productForm.cover_image || '',
                    }),
                ];
            },
            removeSkuRow(index) {
                if ((this.productForm.skus || []).length <= 1) {
                    notice('请至少保留一个规格。', 'error');
                    return;
                }
                this.productForm.skus.splice(index, 1);
            },
            async switchDetailMode(mode) {
                if (mode === this.detailMode) {
                    return;
                }

                if (mode === 'edit') {
                    this.detailMode = 'edit';
                    await this.$nextTick();
                    await createRichTextEditor({
                        selector: '#product-detail-editor-page',
                        editorId: 'product-detail-editor-page',
                        initialContent: this.productForm.detail_html || '',
                        onChange: (content) => {
                            this.productForm.detail_html = content;
                        },
                        getViewportMode: () => this.detailViewportMode,
                        getHost: () => this.$refs.detailEditorHost,
                    });
                    return;
                }

                const editor = window.tinymce?.get('product-detail-editor-page');
                if (editor) {
                    this.productForm.detail_html = editor.getContent();
                }
                this.detailMode = 'preview';
            },
            async uploadCoverImage(event) {
                const file = event.target.files?.[0];
                if (!file) {
                    return;
                }
                this.uploading.cover = true;
                try {
                    this.productForm.cover_image = await uploadAdminFile(file);
                } catch (error) {
                    notice(error.message, 'error');
                } finally {
                    this.uploading.cover = false;
                    event.target.value = '';
                }
            },
            async uploadGalleryImages(event) {
                const files = Array.from(event.target.files || []);
                if (!files.length) {
                    return;
                }

                const remaining = Math.max(0, 10 - (this.productForm.gallery_images || []).length);
                if (remaining <= 0) {
                    notice('附加图片最多 10 张。', 'error');
                    event.target.value = '';
                    return;
                }

                this.uploading.gallery = true;
                try {
                    const uploads = [];
                    for (const file of files.slice(0, remaining)) {
                        uploads.push(await uploadAdminFile(file));
                    }
                    this.productForm.gallery_images = uniqueList([...(this.productForm.gallery_images || []), ...uploads]).slice(0, 10);
                } catch (error) {
                    notice(error.message, 'error');
                } finally {
                    this.uploading.gallery = false;
                    event.target.value = '';
                }
            },
            removeGalleryImage(index) {
                this.productForm.gallery_images.splice(index, 1);
            },
            async saveProduct() {
                try {
                    const editor = window.tinymce?.get('product-detail-editor-page');
                    if (editor) {
                        this.productForm.detail_html = editor.getContent();
                    }

                    const url = this.productForm.id ? `/mall/api/admin/products/${this.productForm.id}` : '/mall/api/admin/products';
                    const method = this.productForm.id ? 'PUT' : 'POST';
                    const skus = serializeProductSkus(this.productForm.skus || [], this.productForm.cover_image);
                    const primarySku = skus[0] || null;
                    const payload = {
                        id: this.productForm.id,
                        name: this.productForm.name,
                        summary: this.productForm.summary,
                        subtitle: this.productForm.summary,
                        brand: this.productForm.brand,
                        category_id: Number(this.productForm.category_id || 0),
                        price: Number(primarySku?.price || 0),
                        stock_total: skus.reduce((total, sku) => total + Number(sku.stock || 0), 0),
                        cover_image: this.productForm.cover_image,
                        is_on_sale: this.productForm.is_on_sale,
                        support_member_discount: this.productForm.support_member_discount,
                        is_course: this.productForm.is_course,
                        is_new_arrival: this.productForm.is_new_arrival,
                        is_recommended_course: this.productForm.is_recommended_course,
                        detail_html: this.productForm.detail_html,
                        gallery: uniqueList([this.productForm.cover_image, ...(this.productForm.gallery_images || [])]).slice(0, 11),
                        skus,
                    };
                    const saved = await apiRequest(url, { method, body: payload });
                    this.productForm = normalizeProductForm(saved);
                    if (saved?.id) {
                        window.history.replaceState({}, '', `/mall/admin/products/edit?id=${saved.id}`);
                    }
                    notice('商品已保存。');
                    this.detailMode = 'preview';
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            formatMoney,
        }));

        Alpine.data('adminActivityEditorPage', () => ({
            activityForm: normalizeActivityForm(pageData.activity || {}),
            detailMode: 'preview',
            detailViewportMode: 'ratio',
            uploading: { thumbnail: false },
            init() {
                this.handleEditorResize = () => {
                    if (this.detailMode === 'edit') {
                        this.refreshDetailEditorViewport();
                    }
                };
                window.addEventListener('resize', this.handleEditorResize);
            },
            refreshDetailEditorViewport() {
                const editor = window.tinymce?.get('activity-detail-editor-page');
                applyRichTextEditorHeight(editor, this.$refs.detailEditorHost, this.detailViewportMode);
            },
            toggleDetailViewportMode() {
                this.detailViewportMode = this.detailViewportMode === 'ratio' ? 'fluid' : 'ratio';
                this.$nextTick(() => this.refreshDetailEditorViewport());
            },
            async switchDetailMode(mode) {
                if (mode === this.detailMode) {
                    return;
                }

                if (mode === 'edit') {
                    this.detailMode = 'edit';
                    await this.$nextTick();
                    await createRichTextEditor({
                        selector: '#activity-detail-editor-page',
                        editorId: 'activity-detail-editor-page',
                        initialContent: this.activityForm.content_html || '',
                        onChange: (content) => {
                            this.activityForm.content_html = content;
                        },
                        getViewportMode: () => this.detailViewportMode,
                        getHost: () => this.$refs.detailEditorHost,
                    });
                    return;
                }

                const editor = window.tinymce?.get('activity-detail-editor-page');
                if (editor) {
                    this.activityForm.content_html = editor.getContent();
                }
                this.detailMode = 'preview';
            },
            async uploadThumbnailImage(event) {
                const file = event.target.files?.[0];
                if (!file) {
                    return;
                }
                this.uploading.thumbnail = true;
                try {
                    this.activityForm.thumbnail_image = await uploadAdminFile(file);
                } catch (error) {
                    notice(error.message, 'error');
                } finally {
                    this.uploading.thumbnail = false;
                    event.target.value = '';
                }
            },
            async saveActivity() {
                try {
                    const editor = window.tinymce?.get('activity-detail-editor-page');
                    if (editor) {
                        this.activityForm.content_html = editor.getContent();
                    }

                    const url = this.activityForm.id ? `/mall/api/admin/activities/${this.activityForm.id}` : '/mall/api/admin/activities';
                    const method = this.activityForm.id ? 'PUT' : 'POST';
                    const payload = {
                        ...this.activityForm,
                        display_order: Number(this.activityForm.display_order || 0),
                        starts_at: this.activityForm.starts_at ? `${this.activityForm.starts_at.replace('T', ' ')}:00` : null,
                        ends_at: this.activityForm.ends_at ? `${this.activityForm.ends_at.replace('T', ' ')}:00` : null,
                    };
                    const saved = await apiRequest(url, { method, body: payload });
                    this.activityForm = normalizeActivityForm(saved);
                    if (saved?.id) {
                        window.history.replaceState({}, '', `/mall/admin/activities/edit?id=${saved.id}`);
                    }
                    notice('活动已保存。');
                    this.detailMode = 'preview';
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
        }));

        Alpine.data('adminPage', () => ({
            tabs: [
                { key: 'dashboard', label: '数据看板' },
                { key: 'products', label: '商品管理' },
                { key: 'categories', label: '分类管理' },
                { key: 'orders', label: '订单管理' },
                { key: 'users', label: '用户管理' },
                { key: 'members', label: '会员联动' },
                { key: 'activities', label: '活动管理' },
                { key: 'settings', label: '系统设置' },
                { key: 'logs', label: '系统日志' },
            ],
            activeTab: queryParam('tab') || 'dashboard',
            loadedTabs: {},
            pageSizeOptions: [15, 30, 50, 100],
            dashboardCards: [],
            products: [],
            selectedProductIds: [],
            productPager: defaultPager(15),
            productFilters: { category_id: '' },
            productCategoryOptions: [],
            categoryTree: [],
            allCategories: [],
            categories: [],
            expandedCategoryIds: [],
            categoryForm: defaultCategoryForm(),
            draggedCategoryId: null,
            adminOrders: [],
            adminOrderGroup: 'all',
            orderFilters: { keyword: '' },
            orderDetail: null,
            shipOrderForm: defaultShipOrderForm(),
            closeOrderForm: defaultCloseOrderForm(),
            orderGroups: [
                { key: 'all', label: '全部' },
                { key: 'pending_payment', label: '待付款' },
                { key: 'pending_shipment', label: '待发货' },
                { key: 'pending_receipt', label: '待收货' },
                { key: 'completed', label: '已完成' },
                { key: 'closed', label: '已关闭' },
            ],
            orderPager: defaultPager(15),
            users: [],
            userForm: defaultUserForm(),
            userPager: defaultPager(15),
            userMemberKeyword: '',
            memberSearchTimer: null,
            memberOptions: [],
            members: [],
            memberClasses: [],
            memberForm: defaultMemberForm(),
            memberPager: defaultPager(15),
            activities: [],
            settings: normalizeSettings(pageData.settings || {}),
            logs: [],
            logFilters: { level: '', channel: '' },
            logPager: defaultPager(15),
            logLevelOptions: ['debug', 'info', 'warning', 'error'],
            logChannelOptions: [],
            logWatchSuspended: false,
            modals: { category: false, user: false, member: false, orderDetail: false, shipOrder: false, closeOrder: false },
            init() {
                if (!this.tabs.find((tab) => tab.key === this.activeTab)) {
                    this.activeTab = 'dashboard';
                }

                this.$watch('logFilters.level', () => {
                    if (!this.logWatchSuspended && this.activeTab === 'logs') {
                        this.logPager = { ...this.logPager, page: 1 };
                        void this.loadLogs();
                    }
                });
                this.$watch('logFilters.channel', () => {
                    if (!this.logWatchSuspended && this.activeTab === 'logs') {
                        this.logPager = { ...this.logPager, page: 1 };
                        void this.loadLogs();
                    }
                });

                void this.ensureTabLoaded(this.activeTab);
            },
            pageNumbers(meta) {
                return pagerNumbers(meta);
            },
            setPagerPage(key, page, loaderName) {
                const pager = this[key];
                const nextPage = Math.max(1, Math.min(Number(pager.total_pages || 1), Number(page || 1)));
                if (nextPage === Number(pager.page || 1)) {
                    return;
                }
                this[key] = { ...pager, page: nextPage };
                void this[loaderName]();
            },
            setPagerSize(key, size, loaderName) {
                this[key] = { ...this[key], page: 1, page_size: Number(size || 15) };
                void this[loaderName]();
            },
            changeTab(tab) {
                this.activeTab = tab;
                window.history.replaceState({}, '', `/mall/admin?tab=${tab}`);
                void this.ensureTabLoaded(tab);
            },
            async ensureTabLoaded(tab) {
                if (this.loadedTabs[tab]) {
                    return;
                }

                if (tab === 'dashboard') await this.loadDashboard();
                if (tab === 'products') await this.loadProducts();
                if (tab === 'categories') await this.loadCategories();
                if (tab === 'orders') await this.loadAdminOrders();
                if (tab === 'users') await this.loadUsers();
                if (tab === 'members') await this.loadMembers();
                if (tab === 'activities') await this.loadActivities();
                if (tab === 'logs') await this.loadLogs();

                this.loadedTabs[tab] = true;
            },
            async loadDashboard() {
                try {
                    const data = await apiRequest('/mall/api/admin/dashboard');
                    this.dashboardCards = [
                        { key: 'today_orders', label: '今日订单数', value: data.today_orders || 0 },
                        { key: 'today_gmv', label: '今日 GMV', value: formatMoney(data.today_gmv || 0) },
                        { key: 'today_new_users', label: '今日新增用户', value: data.today_new_users || 0 },
                        { key: 'stock_alerts', label: '库存预警', value: data.stock_alerts || 0 },
                    ];

                    await loadScriptOnce('https://cdn.jsdelivr.net/npm/chart.js');
                    this.$nextTick(() => {
                        const canvas = document.getElementById('salesChart');
                        if (!canvas || !window.Chart) {
                            return;
                        }

                        if (window.__salesChart) {
                            window.__salesChart.destroy();
                        }

                        window.__salesChart = new window.Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: (data.recent_sales || []).map((item) => item.sale_date),
                                datasets: [{
                                    label: '近 7 日销售额',
                                    data: (data.recent_sales || []).map((item) => Number(item.amount || 0)),
                                    borderColor: '#9c6737',
                                    backgroundColor: 'rgba(156,103,55,0.12)',
                                    tension: 0.35,
                                    fill: true,
                                }],
                            },
                            options: { responsive: true, maintainAspectRatio: false },
                        });
                    });
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadProducts() {
                try {
                    const query = new URLSearchParams({
                        page: this.productPager.page,
                        page_size: this.productPager.page_size,
                        category_id: this.productFilters.category_id,
                    }).toString();
                    const payload = await apiRequest(`/mall/api/admin/products?${query}`);
                    this.products = payload.data || [];
                    this.productPager = normalizePagerMeta(payload.meta, this.productPager.page_size);
                    this.productCategoryOptions = flattenCategories(payload.filters?.categories || []);
                    this.selectedProductIds = this.selectedProductIds.filter((id) => this.products.some((item) => Number(item.id) === Number(id)));
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            goToProductEditor(id = null) {
                window.location.href = id ? `/mall/admin/products/edit?id=${id}` : '/mall/admin/products/edit';
            },
            async batchProductAction(action) {
                if (!this.selectedProductIds.length) {
                    notice('请先选择商品。', 'error');
                    return;
                }

                try {
                    await apiRequest('/mall/api/admin/products/batch', {
                        method: 'POST',
                        body: { product_ids: this.selectedProductIds, action },
                    });
                    notice('批量操作已完成。');
                    await this.loadProducts();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadCategories() {
                try {
                    const tree = await apiRequest('/mall/api/admin/categories');
                    this.categoryTree = tree || [];
                    this.allCategories = flattenCategories(this.categoryTree);
                    this.expandedCategoryIds = this.allCategories
                        .filter((item) => Number(item.depth || 0) === 0)
                        .map((item) => Number(item.id));
                    this.rebuildCategoryRows();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            rebuildCategoryRows() {
                this.categories = buildVisibleCategoryRows(this.categoryTree, new Set(this.expandedCategoryIds));
                this.allCategories = flattenCategories(this.categoryTree);
            },
            categoryLabel(item) {
                return categoryOptionLabel(item);
            },
            toggleCategoryExpand(item) {
                if (!item.has_children) {
                    return;
                }

                const id = Number(item.id);
                if (this.expandedCategoryIds.includes(id)) {
                    this.expandedCategoryIds = this.expandedCategoryIds.filter((value) => value !== id);
                } else {
                    this.expandedCategoryIds = [...this.expandedCategoryIds, id];
                }
                this.rebuildCategoryRows();
            },
            parentCategoryName(parentId) {
                if (!Number(parentId)) {
                    return '一级分类';
                }
                const parent = this.allCategories.find((item) => Number(item.id) === Number(parentId));
                return parent ? parent.name : '未找到';
            },
            categoryParentOptions() {
                return this.allCategories.filter((item) => Number(item.id) !== Number(this.categoryForm.id || 0));
            },
            syncCategoryLevel() {
                const parent = this.allCategories.find((item) => Number(item.id) === Number(this.categoryForm.parent_id || 0));
                this.categoryForm.level = parent ? Number(parent.level || 0) + 1 : 1;
            },
            openCategoryModal(item = null) {
                if (item) {
                    this.categoryForm = {
                        id: item.id,
                        name: item.name,
                        parent_id: item.parent_id ? String(item.parent_id) : '0',
                        level: item.level,
                    };
                } else {
                    this.categoryForm = defaultCategoryForm();
                }
                this.syncCategoryLevel();
                this.modals.category = true;
            },
            closeCategoryModal() {
                this.modals.category = false;
                this.categoryForm = defaultCategoryForm();
            },
            dragCategory(id) {
                this.draggedCategoryId = id;
            },
            async dropCategory(targetId) {
                if (!this.draggedCategoryId || this.draggedCategoryId === targetId) {
                    return;
                }

                const draggedIndex = this.categories.findIndex((item) => item.id === this.draggedCategoryId);
                const targetIndex = this.categories.findIndex((item) => item.id === targetId);
                if (draggedIndex < 0 || targetIndex < 0) {
                    return;
                }

                const [dragged] = this.categories.splice(draggedIndex, 1);
                this.categories.splice(targetIndex, 0, dragged);
                this.categories = this.categories.map((item, index) => ({ ...item, sort_order: index + 1 }));

                try {
                    await apiRequest('/mall/api/admin/categories/sort', {
                        method: 'POST',
                        body: {
                            items: this.categories.map((item) => ({
                                id: item.id,
                                parent_id: item.parent_id,
                                sort_order: item.sort_order,
                            })),
                        },
                    });
                    notice('分类排序已更新。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async saveCategory() {
                try {
                    const url = this.categoryForm.id ? `/mall/api/admin/categories/${this.categoryForm.id}` : '/mall/api/admin/categories';
                    const method = this.categoryForm.id ? 'PUT' : 'POST';
                    await apiRequest(url, {
                        method,
                        body: {
                            ...this.categoryForm,
                            parent_id: Number(this.categoryForm.parent_id || 0),
                            level: Number(this.categoryForm.level || 1),
                        },
                    });
                    notice('分类已保存。');
                    this.closeCategoryModal();
                    await this.loadCategories();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            switchAdminOrderGroup(group) {
                this.adminOrderGroup = group;
                this.orderPager = { ...this.orderPager, page: 1 };
                void this.loadAdminOrders();
            },
            applyOrderSearch() {
                this.orderPager = { ...this.orderPager, page: 1 };
                void this.loadAdminOrders();
            },
            resetOrderSearch() {
                this.orderFilters.keyword = '';
                this.orderPager = { ...this.orderPager, page: 1 };
                void this.loadAdminOrders();
            },
            async loadAdminOrders() {
                try {
                    const query = new URLSearchParams({
                        group: this.adminOrderGroup,
                        page: this.orderPager.page,
                        page_size: this.orderPager.page_size,
                        keyword: this.orderFilters.keyword.trim(),
                    }).toString();
                    const payload = await apiRequest(`/mall/api/admin/orders?${query}`);
                    this.adminOrders = payload.items || [];
                    this.orderPager = normalizePagerMeta(payload.meta, this.orderPager.page_size);
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            openShipOrderModal(order) {
                this.shipOrderForm = {
                    id: Number(order?.id || 0) || null,
                    order_no: String(order?.order_no || ''),
                    shipping_company: '顺丰速运',
                    shipping_no: '',
                };
                this.modals.shipOrder = true;
            },
            closeShipOrderModal() {
                this.modals.shipOrder = false;
                this.shipOrderForm = defaultShipOrderForm();
            },
            async submitShipOrder() {
                if (!this.shipOrderForm.id) {
                    return;
                }

                try {
                    await apiRequest(`/mall/api/admin/orders/${this.shipOrderForm.id}/ship`, {
                        method: 'POST',
                        body: {
                            shipping_company: this.shipOrderForm.shipping_company,
                            shipping_no: this.shipOrderForm.shipping_no,
                        },
                    });
                    notice('订单已发货。');
                    this.closeShipOrderModal();
                    await this.loadAdminOrders();
                    if (this.modals.orderDetail && this.orderDetail?.id) {
                        await this.viewAdminOrderDetail(this.orderDetail.id);
                    }
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            openCloseOrderModal(order) {
                this.closeOrderForm = {
                    id: Number(order?.id || 0) || null,
                    order_no: String(order?.order_no || ''),
                    reason: '管理后台关闭订单',
                };
                this.modals.closeOrder = true;
            },
            closeCloseOrderModal() {
                this.modals.closeOrder = false;
                this.closeOrderForm = defaultCloseOrderForm();
            },
            async submitCloseOrder() {
                if (!this.closeOrderForm.id) {
                    return;
                }

                try {
                    await apiRequest(`/mall/api/admin/orders/${this.closeOrderForm.id}/close`, {
                        method: 'POST',
                        body: { reason: this.closeOrderForm.reason },
                    });
                    notice('订单已关闭。');
                    this.closeCloseOrderModal();
                    await this.loadAdminOrders();
                    if (this.modals.orderDetail && this.orderDetail?.id) {
                        await this.viewAdminOrderDetail(this.orderDetail.id);
                    }
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async viewAdminOrderDetail(orderOrId) {
                const orderId = typeof orderOrId === 'object' ? Number(orderOrId?.id || 0) : Number(orderOrId || 0);
                if (!orderId) {
                    return;
                }

                try {
                    this.orderDetail = await apiRequest(`/mall/api/admin/orders/${orderId}`);
                    this.modals.orderDetail = true;
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            closeOrderDetailModal() {
                this.modals.orderDetail = false;
                this.orderDetail = null;
            },
            async loadUsers() {
                try {
                    const query = new URLSearchParams({
                        page: this.userPager.page,
                        page_size: this.userPager.page_size,
                    }).toString();
                    const payload = await apiRequest(`/mall/api/admin/users?${query}`);
                    this.users = payload.items || [];
                    this.userPager = normalizePagerMeta(payload.meta, this.userPager.page_size);
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadMemberOptions(keyword = '') {
                try {
                    const query = new URLSearchParams({
                        keyword: keyword || '',
                        page: 1,
                        page_size: 20,
                    }).toString();
                    const payload = await apiRequest(`/mall/api/admin/members?${query}`);
                    this.memberOptions = payload.items || [];
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            scheduleMemberSearch() {
                window.clearTimeout(this.memberSearchTimer);
                this.memberSearchTimer = window.setTimeout(() => {
                    void this.loadMemberOptions(this.userMemberKeyword.trim());
                }, 260);
            },
            async prefillUserMemberSearch(memberId) {
                if (!memberId) {
                    this.userMemberKeyword = '';
                    this.memberOptions = [];
                    return;
                }

                await this.loadMemberOptions(String(memberId));
                const selected = this.memberOptions.find((item) => Number(item.fid) === Number(memberId));
                this.userMemberKeyword = selected ? `${selected.fname}（${selected.fnumber}）` : `已绑定会员 #${memberId}`;
            },
            selectUserMember(item) {
                this.userForm.membership_member_id = String(item.fid);
                this.userMemberKeyword = `${item.fname}（${item.fnumber}）`;
                this.memberOptions = [item];
            },
            clearUserMemberBinding() {
                this.userForm.membership_member_id = '';
                this.userMemberKeyword = '';
                this.memberOptions = [];
            },
            openUserModal(item = null) {
                this.userForm = item
                    ? {
                        id: item.id,
                        nickname: item.nickname,
                        phone: item.phone,
                        password: '',
                        membership_member_id: item.membership_member_id ? String(item.membership_member_id) : '',
                        allow_duplicate_membership: false,
                        status: item.status || 'active',
                    }
                    : defaultUserForm();
                this.modals.user = true;
                this.userMemberKeyword = '';
                this.memberOptions = [];
                if (item?.membership_member_id) {
                    void this.prefillUserMemberSearch(item.membership_member_id);
                }
            },
            closeUserModal() {
                this.modals.user = false;
                this.userForm = defaultUserForm();
                this.userMemberKeyword = '';
                this.memberOptions = [];
            },
            async saveUser() {
                const url = this.userForm.id ? `/mall/api/admin/users/${this.userForm.id}` : '/mall/api/admin/users';
                const method = this.userForm.id ? 'PUT' : 'POST';
                const payload = {
                    ...this.userForm,
                    role: 'customer',
                    status: this.userForm.status || 'active',
                    membership_member_id: this.userForm.membership_member_id || '',
                };

                try {
                    await apiRequest(url, { method, body: payload });
                    notice(this.userForm.id ? '用户已保存。' : '用户已保存，初始密码为手机号后 8 位。');
                    this.closeUserModal();
                    await this.loadUsers();
                } catch (error) {
                    if (error.errorCode === 'confirm_required') {
                        const boundUser = error.data?.bound_user || {};
                        const identity = boundUser.phone || boundUser.username || '';
                        const label = [boundUser.nickname || boundUser.phone || boundUser.username || '未知用户', identity ? `（${identity}）` : ''].join('');
                        const confirmed = window.confirm(`${error.message}\n当前绑定用户：${label}`);
                        if (!confirmed) {
                            return;
                        }

                        try {
                            await apiRequest(url, {
                                method,
                                body: {
                                    ...payload,
                                    allow_duplicate_membership: true,
                                },
                            });
                            notice('用户已保存。');
                            this.closeUserModal();
                            await this.loadUsers();
                        } catch (retryError) {
                            notice(retryError.message, 'error');
                        }
                        return;
                    }

                    notice(error.message, 'error');
                }
            },
            async toggleUserStatus(item) {
                const nextStatus = item.status === 'active' ? 'disabled' : 'active';
                const actionLabel = nextStatus === 'active' ? '启用' : '禁用';
                if (!window.confirm(`确认要${actionLabel}用户“${item.nickname || item.phone || '未命名用户'}”吗？`)) {
                    return;
                }

                try {
                    await apiRequest(`/mall/api/admin/users/${item.id}/status`, {
                        method: 'POST',
                        body: { status: nextStatus },
                    });
                    notice(`用户已${actionLabel}。`);
                    await this.loadUsers();

                    if (Number(item.id) === Number(bootstrap.currentUser?.id || 0) && nextStatus !== 'active') {
                        window.location.href = '/mall/login';
                    }
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async resetUserPassword(item) {
                if (!window.confirm(`确认将“${item.nickname || item.phone || '未命名用户'}”的密码重置为手机号后 8 位吗？`)) {
                    return;
                }

                try {
                    await apiRequest(`/mall/api/admin/users/${item.id}/reset-password`, {
                        method: 'POST',
                        body: {},
                    });
                    notice('密码已重置为手机号后 8 位。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            orderStatusLabel,
            paymentMethodLabel,
            adminOrderCanShip,
            adminOrderCanClose,
            orderReceiverAddress,
            orderItemSpecLabel,
            orderClosedReasonLabel,
            orderHasShippingInfo,
            orderHasCloseInfo,
            copyText,
            async loadMembers() {
                try {
                    const query = new URLSearchParams({
                        page: this.memberPager.page,
                        page_size: this.memberPager.page_size,
                    }).toString();
                    const payload = await apiRequest(`/mall/api/admin/members?${query}`);
                    this.members = payload.items || [];
                    this.memberPager = normalizePagerMeta(payload.meta, this.memberPager.page_size);
                    if (!this.memberClasses.length) {
                        this.memberClasses = await apiRequest('/mall/api/admin/member-classes');
                    }
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            openMemberModal(item = null) {
                this.memberForm = item
                    ? {
                        ...defaultMemberForm(),
                        ...item,
                        id: item.id || item.fid,
                        initial_amount: item.faccruedamount || 0,
                        fbalance: item.fbalance || 0,
                        fclassesid: item.fclassesid ? String(item.fclassesid) : '',
                    }
                    : defaultMemberForm();
                this.modals.member = true;
                if (!this.memberClasses.length) {
                    void this.loadMembers();
                }
            },
            closeMemberModal() {
                this.modals.member = false;
                this.memberForm = defaultMemberForm();
            },
            fillMemberClassName() {
                const selected = this.memberClasses.find((item) => Number(item.fid) === Number(this.memberForm.fclassesid));
                this.memberForm.fclassesname = selected ? selected.fname : '';
            },
            async saveMember() {
                try {
                    this.fillMemberClassName();
                    const url = this.memberForm.id ? `/mall/api/admin/members/${this.memberForm.id}` : '/mall/api/admin/members';
                    const method = this.memberForm.id ? 'PUT' : 'POST';
                    await apiRequest(url, {
                        method,
                        body: {
                            ...this.memberForm,
                            initial_amount: Number(this.memberForm.initial_amount || 0),
                            fbalance: Number(this.memberForm.fbalance || 0),
                        },
                    });
                    notice('会员已保存。');
                    this.closeMemberModal();
                    await this.loadMembers();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async adjustMemberBalance(id) {
                const amount = Number(this.memberForm.adjust_amount || 0);
                if (!amount) {
                    notice('请输入调整金额。', 'error');
                    return;
                }

                try {
                    await apiRequest(`/mall/api/admin/members/${id}/balance`, {
                        method: 'POST',
                        body: {
                            amount,
                            mark: this.memberForm.adjust_mark || '管理后台手动调整余额',
                        },
                    });
                    notice('会员余额已调整。');
                    await this.loadMembers();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadActivities() {
                try {
                    this.activities = await apiRequest('/mall/api/admin/activities');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            goToActivityEditor(id = null) {
                window.location.href = id ? `/mall/admin/activities/edit?id=${id}` : '/mall/admin/activities/edit';
            },
            async saveMembershipSettings() {
                try {
                    await apiRequest('/mall/api/admin/settings/membership_mysql', { method: 'POST', body: this.settings.membership_mysql });
                    notice('会员系统配置已保存。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async saveLogSettings() {
                try {
                    await apiRequest('/mall/api/admin/settings/log', { method: 'POST', body: this.settings.log });
                    notice('日志配置已保存。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async saveWechatPaySettings() {
                try {
                    await apiRequest('/mall/api/admin/settings/wechat_pay', { method: 'POST', body: this.settings.wechat_pay });
                    notice('微信支付配置已保存。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async saveServiceAccountSettings() {
                try {
                    await apiRequest('/mall/api/admin/settings/wechat_service_account', { method: 'POST', body: this.settings.wechat_service_account });
                    notice('公众号配置已保存。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async saveNotificationSettings() {
                try {
                    this.settings.notifications = normalizeNotificationSettings(this.settings.notifications);
                    await apiRequest('/mall/api/admin/settings/notifications', { method: 'POST', body: this.settings.notifications });
                    notice('通知配置已保存。');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async testMembership() {
                try {
                    const data = await apiRequest('/mall/api/admin/settings/test-membership', { method: 'POST', body: this.settings.membership_mysql });
                    notice(`${data.message} 当前会员数：${data.total_members}`);
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async testWechatPay() {
                try {
                    const data = await apiRequest('/mall/api/admin/settings/test-wechat-pay', { method: 'POST', body: this.settings.wechat_pay });
                    notice(data.message);
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async testServiceAccount() {
                try {
                    const data = await apiRequest('/mall/api/admin/settings/test-service-account', { method: 'POST', body: this.settings.wechat_service_account });
                    notice(data.message);
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            resetLogFilters() {
                this.logWatchSuspended = true;
                this.logFilters = { level: '', channel: '' };
                this.logWatchSuspended = false;
                this.logPager = { ...this.logPager, page: 1 };
                void this.loadLogs();
            },
            async loadLogs() {
                try {
                    const query = new URLSearchParams({
                        ...this.logFilters,
                        page: this.logPager.page,
                        page_size: this.logPager.page_size,
                    }).toString();
                    const payload = await apiRequest(`/mall/api/admin/logs?${query}`);
                    this.logs = payload.items || [];
                    this.logPager = normalizePagerMeta(payload.meta, this.logPager.page_size);
                    this.logLevelOptions = payload.levels?.length ? payload.levels : this.logLevelOptions;
                    this.logChannelOptions = payload.channels || [];
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            formatMoney,
        }));
    });
})();
