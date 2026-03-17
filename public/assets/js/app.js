(() => {
    const bootstrap = window.MALL_BOOTSTRAP || {};
    const pageData = window.PAGE_DATA || {};
    const tinymceApiKey = 'esazkmyz3gahrj2teqtvimt91wlrracqp3k2ig8zuushd1n6';
    const tinymceScriptSrc = `https://cdn.tiny.cloud/1/${tinymceApiKey}/tinymce/7/tinymce.min.js`;

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

    const ensureTinyMce = () => loadScriptOnce(tinymceScriptSrc);

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

    const syncHeaderOffset = () => {
        const header = document.querySelector('header');
        const offset = header ? Math.ceil(header.getBoundingClientRect().height) + 16 : 80;
        document.documentElement.style.setProperty('--mall-header-offset', `${offset}px`);
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
        const formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());
        const response = await apiRequest('/mall/api/admin/upload', { method: 'POST', body: formData });
        return response.location;
    };

    const uploadAdminFile = async (file) => {
        const formData = new FormData();
        formData.append('file', file, file.name);
        const response = await apiRequest('/mall/api/admin/upload', { method: 'POST', body: formData });
        return response.location;
    };

    const defaultHomeFilters = () => ({
        keyword: '',
        brand: '',
        sort: 'newest',
        price_min: '',
        price_max: '',
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

    const normalizeProductForm = (item = {}) => ({
        id: item.id ?? null,
        name: item.name || '',
        subtitle: item.subtitle || '',
        brand: item.brand || '',
        category_id: item.category_id !== undefined && item.category_id !== null ? String(item.category_id) : '',
        price: item.price ?? '',
        market_price: item.market_price ?? '',
        rating: item.rating ?? 4.8,
        sales_count: item.sales_count ?? 0,
        cover_image: item.cover_image || '',
        is_on_sale: Number(item.is_on_sale ?? 1) === 1,
        support_member_discount: Number(item.support_member_discount ?? 1) === 1,
        is_course: Number(item.is_course ?? 0) === 1,
        is_new_arrival: Number(item.is_new_arrival ?? 0) === 1,
        is_recommended_course: Number(item.is_recommended_course ?? 0) === 1,
        quick_view_text: item.quick_view_text || '',
        detail_html: item.detail_html || '',
        stock_total: item.stock_total ?? 1,
    });

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

    const normalizeSettings = (settings = {}) => ({
        membership_mysql: { host: '', port: '3306', database: '', username: '', password: '', charset: 'utf8mb4', ...(settings.membership_mysql || {}) },
        log: { min_level: 'info', retention_days: '30', max_size_mb: '10', ...(settings.log || {}) },
        wechat_pay: { app_id: '', merchant_id: '', merchant_serial_no: '', pay_mode: 'JSAPI', notify_url: '', api_v3_key: '', private_key_content: '', platform_cert_content: '', ...(settings.wechat_pay || {}) },
        wechat_service_account: { app_id: '', app_secret: '', ...(settings.wechat_service_account || {}) },
        notifications: {
            admin_paid_enabled: '1',
            admin_paid_template_id: '',
            admin_cancelled_enabled: '1',
            admin_cancelled_template_id: '',
            user_created_enabled: '1',
            user_created_template_id: '',
            user_paid_enabled: '1',
            user_paid_template_id: '',
            user_shipped_enabled: '1',
            user_shipped_template_id: '',
            user_completed_enabled: '1',
            user_completed_template_id: '',
            user_closed_enabled: '1',
            user_closed_template_id: '',
            ...(settings.notifications || {}),
        },
    });

    const defaultCategoryForm = () => ({ id: null, name: '', parent_id: '0', level: 1 });
    const defaultUserForm = () => ({ id: null, username: '', nickname: '', phone: '', password: '', membership_member_id: '', allow_duplicate_membership: false, status: 'active' });
    const defaultMemberForm = () => ({ id: null, fnumber: '', fname: '', fclassesid: '', fclassesname: '', initial_amount: 0, fbalance: 0, fmark: '', adjust_amount: '', adjust_mark: '' });

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
            bindLogoutAction();
        }, { once: true });
    } else {
        syncHeaderOffset();
        bindLogoutAction();
    }

    window.addEventListener('resize', syncHeaderOffset);

    document.addEventListener('alpine:init', () => {
        Alpine.data('loginPage', () => ({
            form: { username: '', password: '' },
            async submit() {
                try {
                    const data = await apiRequest('/mall/api/auth/login', {
                        method: 'POST',
                        body: this.form,
                    });
                    notice('登录成功。');
                    window.location.href = data.user.role === 'admin' ? '/mall/admin' : '/mall';
                } catch (error) {
                    notice(error.message, 'error');
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
            filters: defaultHomeFilters(),
            quickView: { open: false, data: null },
            filterTimer: null,
            filterWatchSuspended: false,
            init() {
                ['keyword', 'brand', 'sort', 'price_min', 'price_max'].forEach((key) => {
                    this.$watch(`filters.${key}`, () => {
                        if (this.filterWatchSuspended) {
                            return;
                        }
                        this.scheduleFilterReload();
                    });
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
                    this.quickView.open = true;
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
            quantity: 1,
            skuOptions: {},
            selectedOptions: {},
            currentSku: null,
            init() {
                this.gallery = [this.product.cover_image, ...(this.product.gallery || [])].filter(Boolean);
                this.activeImage = this.gallery[0] || '';
                this.skuOptions = buildSkuOptions(this.product.skus || []);
                Object.entries(this.skuOptions).forEach(([name, values]) => {
                    this.selectedOptions[name] = values[0];
                });
                this.resolveSku();
            },
            selectOption(name, value) {
                this.selectedOptions[name] = value;
                this.resolveSku();
            },
            resolveSku() {
                this.currentSku = (this.product.skus || []).find((sku) => {
                    return Object.entries(this.selectedOptions).every(([name, value]) => (sku.attributes || {})[name] === value);
                }) || (this.product.skus || [])[0] || null;
            },
            get currentPrice() {
                return this.currentSku?.price || this.product.price || 0;
            },
            get currentStock() {
                return this.currentSku?.stock || this.product.stock_total || 0;
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
            activeTab: 'profile',
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
                void this.bootstrapPage();
            },
            emptyAddressForm() {
                return { id: null, receiver_name: '', receiver_phone: '', province: '', city: '', district: '', detail_address: '', is_default: true };
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
                window.location.href = `/mall/order-result?order_id=${id}`;
            },
            async cancelOrder(id) {
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
            init() {
                void this.loadCart();
            },
            async loadCart() {
                try {
                    this.cart = await apiRequest('/mall/api/cart');
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async changeQty(item, quantity) {
                try {
                    this.cart = await apiRequest(`/mall/api/cart/${item.id}`, { method: 'PUT', body: { quantity } });
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
            formatMoney,
        }));

        Alpine.data('checkoutPage', () => ({
            cart: pageData.cart || { items: [], summary: {} },
            addresses: pageData.addresses || [],
            selectedAddressId: pageData.addresses?.[0]?.id || null,
            async createOrder(paymentMethod) {
                if (!this.selectedAddressId) {
                    notice('请选择收货地址。', 'error');
                    return;
                }
                try {
                    const order = await apiRequest('/mall/api/orders', {
                        method: 'POST',
                        body: { address_id: this.selectedAddressId },
                    });

                    if (paymentMethod === 'balance') {
                        await apiRequest(`/mall/api/orders/${order.id}/pay/balance`, { method: 'POST', body: {} });
                        window.location.href = `/mall/order-result?order_id=${order.id}`;
                        return;
                    }

                    const payResult = await apiRequest(`/mall/api/orders/${order.id}/pay/wechat`, { method: 'POST', body: {} });
                    if (payResult.mode === 'H5' && payResult.pay_url) {
                        window.location.href = payResult.pay_url;
                        return;
                    }

                    if (window.WeixinJSBridge && payResult.pay_params) {
                        window.WeixinJSBridge.invoke('getBrandWCPayRequest', payResult.pay_params, () => {
                            window.location.href = `/mall/order-result?order_id=${order.id}`;
                        });
                        return;
                    }

                    notice(payResult.message || '微信支付参数已生成，请在微信环境中完成支付。');
                    window.location.href = `/mall/order-result?order_id=${order.id}`;
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            formatMoney,
        }));

        Alpine.data('bindWechatPage', () => ({
            bindUrl: '',
            async loadBindUrl() {
                try {
                    const data = await apiRequest('/mall/api/wechat/bind-url');
                    this.bindUrl = data.authorize_url;
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
        }));

        Alpine.data('orderResultPage', () => ({
            order: null,
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
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            formatMoney,
        }));

        Alpine.data('adminProductEditorPage', () => ({
            categories: flattenCategories(pageData.categories || []),
            productForm: normalizeProductForm(pageData.product || {}),
            detailMode: 'preview',
            uploading: { cover: false },
            init() {
                if (!this.productForm.category_id && this.categories[0]) {
                    this.productForm.category_id = String(this.categories[0].id);
                }
            },
            categoryLabel: categoryOptionLabel,
            async switchDetailMode(mode) {
                if (mode === this.detailMode) {
                    return;
                }

                if (mode === 'edit') {
                    this.detailMode = 'edit';
                    await ensureTinyMce();
                    this.$nextTick(() => {
                        const existingEditor = window.tinymce?.get('product-detail-editor-page');
                        if (existingEditor) {
                            existingEditor.setContent(this.productForm.detail_html || '');
                            return;
                        }

                        window.tinymce?.init({
                            selector: '#product-detail-editor-page',
                            height: 420,
                            menubar: false,
                            plugins: 'lists link image table code paste',
                            toolbar: 'undo redo | styles | bold italic | alignleft aligncenter alignright | bullist numlist | image link table | code',
                            automatic_uploads: true,
                            paste_data_images: true,
                            convert_urls: false,
                            relative_urls: false,
                            remove_script_host: false,
                            branding: false,
                            promotion: false,
                            setup: (editor) => {
                                editor.on('init', () => editor.setContent(this.productForm.detail_html || ''));
                                editor.on('change keyup input undo redo SetContent', () => {
                                    this.productForm.detail_html = editor.getContent();
                                });
                            },
                            images_upload_handler: editorUploadHandler,
                        });
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
            async saveProduct() {
                try {
                    const editor = window.tinymce?.get('product-detail-editor-page');
                    if (editor) {
                        this.productForm.detail_html = editor.getContent();
                    }

                    const url = this.productForm.id ? `/mall/api/admin/products/${this.productForm.id}` : '/mall/api/admin/products';
                    const method = this.productForm.id ? 'PUT' : 'POST';
                    const payload = {
                        ...this.productForm,
                        category_id: Number(this.productForm.category_id || 0),
                        price: Number(this.productForm.price || 0),
                        market_price: Number(this.productForm.market_price || 0),
                        rating: Number(this.productForm.rating || 0),
                        sales_count: Number(this.productForm.sales_count || 0),
                        stock_total: Number(this.productForm.stock_total || 0),
                        gallery: [this.productForm.cover_image].filter(Boolean),
                        skus: [{
                            price: Number(this.productForm.price || 0),
                            stock: Number(this.productForm.stock_total || 0),
                            cover_image: this.productForm.cover_image,
                            attributes: { 规格: this.productForm.is_course ? '课程版' : '默认规格' },
                        }],
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
            uploading: { thumbnail: false },
            async switchDetailMode(mode) {
                if (mode === this.detailMode) {
                    return;
                }

                if (mode === 'edit') {
                    this.detailMode = 'edit';
                    await ensureTinyMce();
                    this.$nextTick(() => {
                        const existingEditor = window.tinymce?.get('activity-detail-editor-page');
                        if (existingEditor) {
                            existingEditor.setContent(this.activityForm.content_html || '');
                            return;
                        }

                        window.tinymce?.init({
                            selector: '#activity-detail-editor-page',
                            height: 420,
                            menubar: false,
                            plugins: 'lists link image table code paste',
                            toolbar: 'undo redo | styles | bold italic | alignleft aligncenter alignright | bullist numlist | image link table | code',
                            automatic_uploads: true,
                            paste_data_images: true,
                            convert_urls: false,
                            relative_urls: false,
                            remove_script_host: false,
                            branding: false,
                            promotion: false,
                            setup: (editor) => {
                                editor.on('init', () => editor.setContent(this.activityForm.content_html || ''));
                                editor.on('change keyup input undo redo SetContent', () => {
                                    this.activityForm.content_html = editor.getContent();
                                });
                            },
                            images_upload_handler: editorUploadHandler,
                        });
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
            categories: [],
            categoryForm: defaultCategoryForm(),
            draggedCategoryId: null,
            adminOrders: [],
            adminOrderGroup: 'all',
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
            modals: { category: false, user: false, member: false },
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
                    }).toString();
                    const payload = await apiRequest(`/mall/api/admin/products?${query}`);
                    this.products = payload.data || [];
                    this.productPager = normalizePagerMeta(payload.meta, this.productPager.page_size);
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
                    this.categories = flattenCategories(tree);
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            categoryLabel(item) {
                return categoryOptionLabel(item);
            },
            parentCategoryName(parentId) {
                if (!Number(parentId)) {
                    return '一级分类';
                }
                const parent = this.categories.find((item) => Number(item.id) === Number(parentId));
                return parent ? parent.name : '未找到';
            },
            categoryParentOptions() {
                return this.categories.filter((item) => Number(item.id) !== Number(this.categoryForm.id || 0));
            },
            syncCategoryLevel() {
                const parent = this.categories.find((item) => Number(item.id) === Number(this.categoryForm.parent_id || 0));
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
            async loadAdminOrders() {
                try {
                    const query = new URLSearchParams({
                        group: this.adminOrderGroup,
                        page: this.orderPager.page,
                        page_size: this.orderPager.page_size,
                    }).toString();
                    const payload = await apiRequest(`/mall/api/admin/orders?${query}`);
                    this.adminOrders = payload.items || [];
                    this.orderPager = normalizePagerMeta(payload.meta, this.orderPager.page_size);
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async ship(order) {
                const shippingCompany = window.prompt('请输入物流公司', '顺丰速运');
                if (!shippingCompany) {
                    return;
                }
                const shippingNo = window.prompt('请输入运单号');
                if (!shippingNo) {
                    return;
                }

                try {
                    await apiRequest(`/mall/api/admin/orders/${order.id}/ship`, {
                        method: 'POST',
                        body: { shipping_company: shippingCompany, shipping_no: shippingNo },
                    });
                    notice('订单已发货。');
                    await this.loadAdminOrders();
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async closeAdminOrder(id) {
                const reason = window.prompt('请输入关闭原因', '管理后台关闭订单');
                if (!reason) {
                    return;
                }

                try {
                    await apiRequest(`/mall/api/admin/orders/${id}/close`, {
                        method: 'POST',
                        body: { reason },
                    });
                    notice('订单已关闭。');
                    await this.loadAdminOrders();
                } catch (error) {
                    notice(error.message, 'error');
                }
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
                    if (!this.memberOptions.length) {
                        await this.loadMemberOptions();
                    }
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            async loadMemberOptions() {
                try {
                    const bucket = [];
                    let page = 1;
                    let totalPages = 1;

                    do {
                        const payload = await apiRequest(`/mall/api/admin/members?page=${page}&page_size=100`);
                        bucket.push(...(payload.items || []));
                        totalPages = Number(payload.meta?.total_pages || 1);
                        page += 1;
                    } while (page <= totalPages);

                    this.memberOptions = bucket;
                } catch (error) {
                    notice(error.message, 'error');
                }
            },
            openUserModal(item = null) {
                this.userForm = item
                    ? {
                        id: item.id,
                        username: item.username,
                        nickname: item.nickname,
                        phone: item.phone,
                        password: '',
                        membership_member_id: item.membership_member_id ? String(item.membership_member_id) : '',
                        allow_duplicate_membership: false,
                        status: item.status || 'active',
                    }
                    : defaultUserForm();
                this.modals.user = true;
                if (!this.memberOptions.length) {
                    void this.loadMemberOptions();
                }
            },
            closeUserModal() {
                this.modals.user = false;
                this.userForm = defaultUserForm();
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
                        const label = [boundUser.nickname || boundUser.username || '未知用户', boundUser.username ? `（${boundUser.username}）` : ''].join('');
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
                if (!window.confirm(`确认要${actionLabel}用户“${item.nickname || item.username}”吗？`)) {
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
                if (!window.confirm(`确认将“${item.nickname || item.username}”的密码重置为手机号后 8 位吗？`)) {
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
                    if (!this.memberOptions.length) {
                        await this.loadMemberOptions();
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
                    await this.loadMemberOptions();
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
