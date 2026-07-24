# AGENTS.md

## 1) 项目定位
- 本仓库是一个 WordPress 项目，核心业务为原材料价格展示 + 会员订阅（SWPM）。
- 自定义业务代码主要在：
  - `wp-content/themes/ippgi`
  - `wp-content/plugins/ippgi-prices`
  - 根目录运维脚本（如 `import-*.php`、`collect-current-prices.php`）

## 2) 修改范围与边界
- 默认只改自定义代码，不改 WordPress Core（`wp-admin`、`wp-includes`、根目录核心 `wp-*.php`）。
- 涉及支付、会员、定时任务时，优先做“最小改动”，避免影响现有线上流程。
- 不提交密钥、凭证、个人隐私数据。

## 3) 事实来源（Source of Truth）
- 运行代码优先于文档描述。
- `CLAUDE.md`、`GEMINI.md` 用于背景说明；若与代码冲突，以代码为准，并在提交说明里标注差异。

## 4) 关键业务约束
- 会员体系（SWPM）：Basic=2，Plus=4；赠送天数走 bonus 机制（用户 meta）。
- 新用户注册后仍按现有流程返回首页并自动获得完整 7 天 bonus 权限，但当前不显示注册成功欢迎弹窗；bonus 时间计算必须使用真实 Unix 时间戳与 WordPress 站点时区，禁止拿 `current_time('timestamp')` 与真实时间戳直接比较。
- Trial（Level 3）已停用且不再参与任何业务流程；当前项目仅保留 Basic=2、Plus=4 两个实际使用的 SWPM 等级。
- 登录入口：`/login/` 是主登录页；`/membership-login/` 保留兼容。已登录用户访问这两个页面时应立即跳回首页，避免重复维护两套跳转逻辑。
- 页面访问权限：
  - `/prices` 价格列表页当前为“登录即可访问”。
  - `/price-detail` 价格详情页当前为“登录即可访问”。
- 手机号补全：已登录但 `phone` 为空的用户从首页等价格入口继续时显示可关闭的手机号弹窗，直达 `/prices` 不增加手机号硬拦截。弹窗和 `/edit-profile` 共用国家码表、拆分及校验规则；已有国际号码优先按号码前缀选择国家码，旧号码无前缀时再按用户资料中的 `country` 预选，资料为空或无法识别时保持未选择，不按 IP 或界面语言推断。保存格式统一为 `+国家码 本地号码纯数字`，手机号国家码不自动修改资料中的 Country/Region，当前不做短信验证。
- SEO 元数据：
  - 主题通过 `inc/seo.php` 为前台请求统一输出 Meta Description 和 Meta Keywords；新建文章/页面无需额外配置即可自动生成。
  - 文章和页面编辑器中的 `SEO Metadata` 面板可用 `_ippgi_seo_description`、`_ippgi_seo_keywords` 手动覆盖；人工值优先，留空时 Description 按页面专用默认值/摘要/正文/标题回退，Keywords 按标题/分类/标签/站点业务词生成。
  - 登录、个人资料、收藏、邀请、支付结果、价格列表/详情、搜索和 404 等功能页必须保持 `noindex, noarchive`。
  - TranslatePress SEO Pack 负责 Description 与 hreflang 翻译，主题额外注册 Keywords 的属性翻译；若以后启用 Yoast、Rank Math、SEOPress 或 AIOSEO，主题应停止自身 Meta 输出以避免重复标签。
- 首页公告系统：
  - 公告条当前只在首页显示，且已改为参与正常文档流，不再使用 fixed 悬浮覆盖首页内容。
  - 公告条当前插在 `front-page.php` 的 `site-main` 顶部，并通过轻微负 margin 贴紧 header 下边界。
  - 公告后台 `Subscribers Only (Paid Members)` 当前只认 SWPM Plus(4)；不要把 Basic(2) 或 bonus-only 用户计入 paid member。
- 邮件系统：使用 WP Mail SMTP 插件通过 Gmail API 发送邮件。发件人必须与 Gmail 认证账号一致。
- 升级通知邮件收件人：SWPM 自动发出的 `Account Upgrade Notification` 在本站当前实现中应优先发送到 SWPM 会员资料里的邮箱；若支付网关返回邮箱与站内资料邮箱不一致，也以站内资料邮箱为准。
- 邮件通知逻辑（职责分工）：
  - **由 SWPM 插件自动发送**：
    1. 首次 Google 注册成功 (Registration Complete)。
    2. 付费订阅成功后的升级通知 (Account Upgrade Notification)。
    3. 订阅取消/过期 (Subscription Payment Canceled or Expired)。
  - **由自定义代码手动补全**：
    1. 支付成功后的成功模态框、取消状态清理等站内 UI/状态处理，不再重复发送升级邮件。
  - **防冲突原则**：在上述“自动发送点”切勿增加手动 Hook，避免骚扰用户。
- Rendui 价格 API 头约束：
  - `prices/daily`（价格列表）不传自定义请求头。
  - `daily/getByProductSpecAndDate`（实时/详情）已停用，服务端不再请求该接口。
  - `prices/statistics`（统计）需要传 `phone` 头。
  - 当前统一值：`13792171909`。
- 数据表前缀：`ippgi_`（非 `wp_`）。
- 历史价格表当前仅保留美元价格字段：`price_usd`、`price_usd_min`、`price_usd_max`、`price_tax_usd`、`price_tax_usd_min`、`price_tax_usd_max`、`exchange_rate`；不再存储人民币价格列。
- REST 命名空间：`ippgi-prices/v1`。
- 订阅价格修改约束：
  - 前台展示价格目前为月度 `US$29/month`、年度 `US$290/year`。
  - 实际扣款金额不由模板文案决定；若调整订阅价格，必须同时同步主题模板、SWPM Payment Buttons、以及 Stripe 对应的 Price ID，避免“页面显示价格”和“真实扣款金额”不一致。
- 时区与调度：按 Asia/Shanghai（UTC+8）理解业务时间。
- 定时任务关键流程：
  - 00:10：优先基于现有缓存保存历史价格快照，并同时保存缓存中的汇率快照；不刷新最新汇率，不重算价格列表缓存，也不重算已缓存单规格详情中的美元价格。若缓存缺失，允许回源抓取最新价格列表作为兜底。
  - 01:10-08:10、18:10-23:10：已取消，不再执行汇率刷新或缓存 USD 重算。
  - 09:10-17:10：按小时刷新价格。执行顺序为：先清实时价格缓存和汇率缓存、再强制刷新最新汇率、然后生成一组“本次刷新共用”的随机区间因子、再逐个分类获取新数据并立刻按最新汇率转换为 USD；若某分类获取失败则直接保留该分类的旧 USD 缓存，不按最新汇率重算；最后对本次最终结果中的全部分类统一重算 `Latest($)` 区间并写回价格列表缓存。
- 价格列表与首页的 `Latest($)` 当前显示美元区间值而非单点值。每次价格列表刷新时只生成一组全局随机上下浮动因子，并统一应用到本次刷新后的全部产品结果（含成功抓取的新分类与沿用旧缓存的失败分类）：下限因子区间 `0.1%~0.5%`、上限因子区间 `1%~2%`，均包含边界。
- `Latest($)` 的颜色判断不再依赖 `Change` 列；首页与 `/prices` 页当前都默认按含税区间均价对比上一轮含税区间均价决定涨跌颜色。
- 询价表单（首页弹窗与价格详情页内嵌卡片）当前默认所有字段留空，不再根据登录用户信息或当前产品规格自动预填。
- 多语言（TranslatePress）：
  - 当前启用 4 种语言：默认英文走根路径，`/fr/` `/ru/` `/es/` 走子目录；不要把英文也加上子目录前缀。
  - 自动翻译走 Google Translate v2（Developer 版授权下 TP AI 配额为 0，不要切回 TP AI）。
  - header `top-bar` 的语言切换器是主题自定义实现（`header.php` 35-78 + `assets/css/layout.css` + `main.js` 的 `initLanguageSwitcher()`）；TP 默认浮动切换器后台已关闭，不要再启用。
  - `.top-bar` 的 z-index 是 `calc(var(--z-sticky) + 1)`（=201），目的是让语言切换器下拉覆盖 `.site-header`；不要回退到 `var(--z-sticky)`。
- JS 字符串本地化：
  - 所有 JS 中用户可见的英文字符串必须走 `ippgiData.strings.<key>`，不要在 JS / 内联 PHP 中直接写英文 literal。
  - 字典定义在 `inc/template-functions.php::ippgi_get_js_i18n_strings()`；新增 key 时调用 `$tr('English source')`，**不要写 `$tr(__('...', 'ippgi'))`**——`$tr` 内部会先做 gettext 查表，再 fallback 到 `trp_translate`，外面再套 `__()` 会触发双重翻译产生乱译（如 `Mai → Peut`、`May → номер`）。
  - 修改字典逻辑或新增/删除 key 时，必须把 `$cache_key` 中当前版本段升一位（当前为 `v7`），让旧缓存失效。
  - 字典返回值必须经过 `$sanitize` 闭包：剥掉 `<translate-press>` 包裹标签 + `html_entity_decode`；gettext 路径和 trp_translate 路径都要走，不能漏其中一条。
- TranslatePress 钩子前置：
  - `functions.php` 中的 `add_filter('trp_apply_gettext_early', '__return_true');` 是必须的——否则 `wp_localize_script` 阶段调用的 `__('xxx', 'ippgi')` 全部返回原文（TP gettext 默认在 `wp_head` 优先级 100 才挂载，晚于 `wp_enqueue_scripts`）。删除这一行会导致全站 JS 字典回退到机翻，质量大幅下降。
- 模板中的日期/月份渲染：
  - 不要在 PHP 模板里用 `date_i18n('F Y')` 这类输出英文月份名给 TP 翻译——TP 常规字符串机翻会把单独的 "May" 误译成 `номер`/`Peut`/`Puede` 等。
  - 月份 / 日期相关 UI 应留给 JS 通过 `ippgi.strings.months` 渲染（参考 `home.php` 的 `<span data-no-translation>` 占位 + `main.js` 的 `renderCalendar()`）。

## 5) 代码实现约定
- PHP 侧：
  - 所有输入参数做 sanitize/validate；敏感操作校验 nonce/capability。
  - 保持已有 meta key、hook 名称、接口字段兼容，避免破坏历史数据与前端解析。
- 前端（主题 JS + 模板）：
  - 若改 REST 返回结构，必须同步改调用处（首页、价格页、详情页）。
  - 涉及会员可见性的 UI 变更，需覆盖 guest/basic/bonus/plus 状态。
  - 首页中间 banner（`front-page.php` + `assets/css/components.css`）当前要求为“宽度填满容器，按原图比例自动撑高，不裁剪”；避免重新引入固定高度 + `object-fit: cover` 的裁剪方案。
- TranslatePress 翻译变更后的验证：
  - 在 TP 后台改翻译后，**必须**清缓存 + 浏览器硬刷新才能看到效果：`wp transient delete --all --allow-root` + `Cmd+Shift+R`/`Ctrl+Shift+F5`。普通刷新会拿浏览器缓存的旧 HTML（含旧 `ippgiData`）。
  - 排查"翻译没生效"时，先用 Console `ippgiData.strings.<key>` 看字典实际值；和 `document.getElementById(...).textContent` 看 DOM 实际值。两者不一致说明 JS 渲染时机或服务端 PHP 渲染抢先输出了未翻译版本。

## 6) 变更前后检查清单
- 语法与基础检查：
  - `php -l` 检查改动过的 PHP 文件。
- 功能检查（按改动范围选择）：
  - 价格接口：`/prices/category`、`/historical`；`/price` 当前应返回停用错误。
  - 会员流程：登录、订阅状态展示、取消订阅、bonus 生效/到期。
  - 定时任务：不破坏现有 cron hook 与缓存策略。
- 文档同步：
  - 若你修改了业务规则/ID/时序，需同步更新 `CLAUDE.md` 与本文件。

## 7) 运维脚本使用原则
- 根目录导入脚本仅用于运维/补数，执行前确认日期范围与环境（避免误写生产）。
- `import-missing-days.php` 仅补历史单点美元价（`price_usd` / `price_tax_usd`）；历史区间字段需通过单独脚本按日期范围回填。部分补数时应优先恢复当天已有的公共因子，只有当天完全没有区间时才随机生成，并始终保持“每天一组、当天全表共用”。
- `import-missing-days.php` 的日期范围为包含首尾日期的完整自然日：开始时间 `00:00:00`，结束时间 `23:59:59`；任一规格失败时 CLI 必须返回非零退出码，便于发现部分失败并安全重跑。
- 历史统计接口可能返回请求日期范围之外的记录，导入器必须在本地按 `statistics_time` 再过滤后才能写库；部分失败后使用 `--only-missing` 只重试目标范围内尚无记录的规格。
- 历史价格补数时若某一分类因上游错误未进入当前价格缓存，导入器应优先从目标范围之前最近的历史快照读取 `product_spec` 列表（没有前序快照时再读取范围之后最近的快照），不能拿目标范围内可能不完整的快照作为候选规格表。
- 在 Git 仓库与正式网站目录分离的服务器上，根目录补数脚本通过 `IPPGI_WP_ROOT=/home/html/www/ippgi` 加载正式站点，脚本本身不要复制到可公开访问的网站根目录。
- 与汇率相关的导入脚本可能耗时较长，执行前明确预期与回滚方案。

## 8) 提交说明建议
- 提交信息应包含：
  - 改了什么（文件/模块）
  - 为什么改（问题或需求）
  - 风险点与验证结果
