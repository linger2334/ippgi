# IPPGI - 原材料价格行情网站

## 项目概述
这是一个基于 WordPress 的原材料价格行情展示网站，为用户提供实时和历史价格数据。

## 技术栈
- WordPress (最新版本)
- PHP 8.1
- 数据库：MySQL（通过 WordPress wpdb）
- 会员插件：Simple Membership Plugin
- 缓存：WordPress Transients API
- 定时任务：WP-Cron
- 设计：Figma（移动端优先，响应式适配 PC 端）

## 会员系统
- **插件**：Simple Membership Plugin
- **支付方式**：PayPal 订阅 + Stripe 订阅（测试环境使用 Sandbox/Test 模式）
- **会员等级配置**：

| 等级 | SWPM Level ID | 说明 |
|-----|---------------|------|
| Guest | - | 未登录用户，仅查看当天价格 |
| Basic | 2 | 免费注册用户（默认等级），查看当天价格 |
| Plus | 4 | 付费高级会员，查看完整历史数据、图表、数据导出 |

**注意**：
- 新用户注册时默认为 Basic，系统自动通过 bonus 机制给予完整 7 天 Plus 访问权限；注册后仍返回首页，但当前不显示注册成功欢迎弹窗
- bonus 激活/续期只写入 bonus 相关 meta，不会把 SWPM membership_level 改成 4；权限判断统一走 `ippgi_bonus_access_end > 当前时间`
- 主题代码禁止手动升级会员等级到 Plus(4)；升级仅由 SWPM 支付流程自动处理，主题侧只允许降级到 Basic(2)
- Plus 等级在 SWPM 后台设置为 "No Expiry"，所以 SWPM 不会自动降级用户，需要我们的代码在订阅到期时手动处理降级
- Trial (Level 3) 已从业务流程中移除；当前仅使用 Basic (2) 与 Plus (4)，所有赠送天数统一通过 bonus 机制管理
- 付费升级成功后的 `Account Upgrade Notification` 仍由 SWPM 自动发送，但本站主题会在发送前把收件人改写为 SWPM 会员资料中的邮箱；若支付网关回调邮箱与站内资料邮箱不一致，以站内资料邮箱为准

### Bonus 访问机制

所有赠送天数统一由 bonus 机制管理：

| 来源 | 天数 | 说明 |
|------|------|------|
| 新用户注册 | 7 天 | 注册时自动激活 |
| 邀请奖励 | 7 天 | 邀请好友注册成功后获得 |

**User Meta 字段**：
| Meta Key | 说明 |
|---------|------|
| `ippgi_bonus_access_start` | bonus 访问开始时间 |
| `ippgi_bonus_access_end` | bonus 访问到期时间 |
| `ippgi_unused_bonus_days` | 未使用的累积奖励天数（订阅期间累积，订阅到期后激活） |
| `ippgi_registration_bonus_granted` | 注册 7 天奖励发放标记（防止重复发放） |
| `ippgi_registration_referral_processed` | 注册邀请码处理标记（防止重复发放 7 天邀请奖励） |

### SWPM Hook 集成

我们使用以下 SWPM hook 处理订阅生命周期：

| Hook | 触发时机 | 处理函数 | 做什么 |
|------|---------|---------|--------|
| `swpm_payment_ipn_processed` | 首次支付成功、续费成功 | `ippgi_on_payment_success()` | 显示成功模态框、清除取消状态 |
| `swpm_subscription_payment_cancelled` | 订阅到期（取消后到期、续费失败终止） | `ippgi_on_subscription_expired()` | 清除 subscr_id、激活奖励天数或降级为 Basic |
| `swpm_stripe_subscription_updated` | Stripe 订阅更新（`customer.subscription.updated`） | `ippgi_on_stripe_subscription_updated()` | 同步 `cancel_at_period_end` 取消状态和到期时间 |
| `swpm_registration_complete` | 新用户注册 | `ippgi_on_swpm_registration()` | 给予 7 天 bonus 访问、处理邀请码逻辑 |
| `swpm_front_end_registration_complete_user_data` | 前台注册完成（含 Social Login 自动注册） | `ippgi_on_swpm_registration()` | 给予 7 天 bonus 访问、处理邀请码逻辑 |

> 实现细节：`ippgi_register_swpm_hooks()` 在 `init` 优先级 `1` 注册，确保在 Social Login 插件（同样使用 `init`）触发注册动作前已挂载回调，避免漏发注册奖励/邀请奖励。

**订阅到期处理流程**（`ippgi_on_subscription_expired`）：
1. 验证用户当前是 Plus (4)
2. 清除 SWPM 的 `subscr_id`（订阅已结束）
3. 先保障会员等级降为 Basic (2)（仅在当前等级不是 2 时写库）
4. 检查是否有累积奖励天数：
   - 有 → 立即激活奖励访问（不改 SWPM 等级）
   - 无 → 维持 Basic (2)
5. 清除订阅相关 user meta

### SWPM 支付按钮 ID

| 按钮 | SWPM Button ID | 说明 |
|-----|---------------|------|
| PayPal Monthly | 123 | PayPal 月度订阅 |
| PayPal Yearly | 124 | PayPal 年度订阅 |
| Stripe Monthly | 126 | Stripe 月度订阅 |
| Stripe Yearly | 127 | Stripe 年度订阅 |

> 注意：当前支付页模板 `page-payment.php` 中写死引用的按钮 ID 仍是 `168/169/173/174`，与本地数据库现存的 `123/124/126/127` 不一致。后续如继续调整真实扣款逻辑或排查支付异常，应先核对运行环境实际启用的 SWPM Payment Button ID。

### 订阅价格
- 前台展示价格当前已改为：月度 `US$29.00/month`、年度 `US$290.00/year`
- 真实扣款价格仍取决于 SWPM Payment Button 配置与 Stripe 侧 Price ID，修改订阅价格时必须同步更新：
  - 主题模板：`page-subscribe.php`、`page-payment.php`
  - SWPM PayPal 按钮金额
  - SWPM Stripe 按钮绑定的 `stripe_plan_id`
  - Stripe 后台对应的 Price

### 订阅状态获取
- 使用 PayPal/Stripe API 获取真实的下次扣款日期（`next_billing_time` / `current_period_end`）
- PayPal：通过 OAuth2 认证调用 `/v1/billing/subscriptions/{id}` 获取 `billing_info.next_billing_time`
- Stripe：通过 Secret Key 调用 `/v1/subscriptions/{id}` 获取 `current_period_end`
- API 凭证从 SWPM 设置（`swpm-settings`）中读取
- 缓存键：`ippgi_next_billing_` + `md5(subscr_id)`，缓存 1 小时
- 订阅 ID 前缀判断类型：`I-` = PayPal，`sub_` = Stripe

### 取消订阅功能
用户可以从 Profile 页面取消订阅，系统会调用 PayPal/Stripe API 实际取消订阅。

**前端流程**：
1. 用户点击 "Cancel Subscription" 按钮
2. 弹出确认对话框
3. 点击确认后按钮禁用并显示 "Cancelling..."（防止重复点击）
4. 发送 AJAX 请求到 `ippgi_cancel_subscription` action
5. 成功后页面刷新显示 "Cancelled" 状态；失败时恢复按钮状态

**后端处理** (`ippgi_ajax_cancel_subscription`)：
1. 验证用户登录状态和 nonce
2. 获取 SWPM 会员的 `subscr_id`
3. 保存订阅结束日期到 `ippgi_subscription_end_date`（取消后 API 可能不再返回）
4. 根据订阅 ID 前缀判断类型并调用对应 API：
   - `I-` 开头 → `ippgi_cancel_paypal_subscription()`
   - `sub_` 开头 → `ippgi_cancel_stripe_subscription()`
5. 设置本地取消标记（`ippgi_subscription_cancelled`）
6. 清除下次扣款日期缓存
7. 返回成功/失败响应

**PayPal 取消 API** (`ippgi_cancel_paypal_subscription`)：
```
POST https://api-m.paypal.com/v1/billing/subscriptions/{id}/cancel
Authorization: Bearer {access_token}
Content-Type: application/json
Body: {"reason": "Customer requested cancellation"}
```
- 先通过 OAuth2 获取 access_token（`/v1/oauth2/token`）
- 成功返回 HTTP 204
- 取消后用户可继续使用到当前计费周期结束

**Stripe 取消 API** (`ippgi_cancel_stripe_subscription`)：
```
POST https://api.stripe.com/v1/subscriptions/{id}
Authorization: Bearer {secret_key}
Body: cancel_at_period_end=true
```
- 设置 `cancel_at_period_end=true` 而非立即取消
- 用户可继续使用到当前计费周期结束
- Stripe webhook 会在周期结束时通知 SWPM 降级用户

**用户 Meta 字段**：
| Meta Key | 说明 |
|---------|------|
| `ippgi_subscription_cancelled` | 是否已取消订阅（bool） |
| `ippgi_subscription_cancelled_date` | 取消订阅的时间 |
| `ippgi_subscription_end_date` | 取消时保存的订阅结束日期（格式化字符串，重新订阅时清除） |
| `ippgi_payment_just_completed` | 支付刚完成标记，用于显示成功模态框（一次性） |

**相关函数**：
- `ippgi_ajax_cancel_subscription()` - AJAX 处理函数
- `ippgi_cancel_paypal_subscription($subscr_id)` - PayPal API 取消
- `ippgi_cancel_stripe_subscription($subscr_id)` - Stripe API 取消
- `ippgi_on_subscription_expired($ipn_data)` - 处理取消 webhook（区分 PayPal/Stripe）
- `ippgi_on_stripe_subscription_updated($event_data)` - 同步 Stripe Dashboard 取消/恢复状态
- `ippgi_check_expired_cancelled_subscriptions()` - 每日定时任务，处理真正过期的订阅
- `ippgi_get_paypal_next_billing_date($subscr_id)` - 获取 PayPal 订阅结束日期

**取消订阅后的降级机制**（PayPal 和 Stripe 行为不同）：

| 平台 | 取消时 | webhook 时机 | 降级时机 |
|-----|-------|-------------|---------|
| PayPal | 立即发送 IPN | 取消时立即 | 每日定时任务检查到期后降级 |
| Stripe | 标记 `cancel_at_period_end` | 周期结束时 | webhook 触发时立即降级 |

**PayPal 流程**（网站取消）：
1. 用户取消订阅 → 保存结束日期到 `ippgi_subscription_end_date`
2. PayPal 立即发送 IPN → 检测到是 PayPal（`I-` 前缀）且还没到期 → 只清除 subscr_id，保留 Plus 权限
3. 每日午夜定时任务 → 检查 `ippgi_subscription_end_date` → 已过期则降级

**PayPal 流程**（PayPal 后台取消）：
1. 用户在 PayPal 后台取消 → PayPal 立即发送 IPN（没有预先保存的结束日期）
2. 检测到没有 `ippgi_subscription_end_date` → 调用 PayPal API 获取或计算结束日期
3. 保存结束日期，检查是否到期 → 未到期则保留 Plus 权限
4. 每日午夜定时任务 → 检查 `ippgi_subscription_end_date` → 已过期则降级

**Stripe 流程**（网站取消）：
1. 用户取消订阅 → 保存结束日期，Stripe 设置 `cancel_at_period_end=true`
2. 计费周期结束时 Stripe 发送 `customer.subscription.deleted` webhook → 检测到是 Stripe（`sub_` 前缀）→ 直接降级

**Stripe 流程**（Stripe Dashboard 取消）：
- Stripe Dashboard 提供两种取消方式：
  - **Cancel immediately**：立即发送 `customer.subscription.deleted` → 立即降级
  - **Cancel at end of period**：先发送 `customer.subscription.updated`，网站同步标记 cancelled + 到期时间；周期结束时再发送 `customer.subscription.deleted` → 降级
- 系统已监听并转发 Stripe `customer.subscription.updated` 事件，确保用户从 Stripe 后台取消时，Profile 页面也会同步展示取消状态和结束日期

### 支付成功提示
当用户完成 PayPal/Stripe 订阅支付后，返回网站首页会显示成功模态框。

**实现机制**：
1. SWPM 处理支付完成后触发 `swpm_payment_ipn_processed` hook
2. `ippgi_on_payment_success()` 设置 `ippgi_payment_just_completed` user meta
3. 用户被重定向到 Return URL（首页）
4. `wp_footer` hook 检测到该 meta，显示成功模态框并删除 meta（一次性）

**模态框 UI**（基于 Figma 设计稿）：
- 半透明深色遮罩层（`rgba(0, 0, 0, 0.5)`）
- 白色渐变卡片（从天蓝 `#7ecde8` 到白色 55%），圆角 16px
- 绿色圆形打钩图标（30px，`#6abf40`，笔划宽度 5px）
- 标题："You're all set!"（21px，深灰色 `#5e6b71`）
- 描述："Subscription successful! / Full access to all pricing data."（15px，`#515557`，左对齐）
- 蓝色按钮："Continue to iPPGI"（`#1e98d7`，圆角 13px）
- 点击按钮关闭模态框

**CSS 类**：位于 `/assets/css/components.css`
- `.payment-success-overlay` - 全屏遮罩层
- `.payment-success-card` - 居中卡片
- `.payment-success-card__icon` - 打钩图标
- `.payment-success-card__title` - 标题
- `.payment-success-card__desc` - 描述文字
- `.payment-success-card__btn` - 按钮

**注意**：
- 我们使用 `swpm_payment_ipn_processed` hook 而非 `swpm_membership_level_changed`（后者不可靠）
- Toast 组件 `ippgiToast` 仍保留用于其他功能（收藏、复制链接等）
- 新用户注册成功后仍自动获得 7 天 bonus 并按既有登录流程返回首页，但当前不写入欢迎弹窗标记，也不显示注册成功弹窗；付费成功弹窗保持不变。

**Webhook 配置**：
- PayPal：SWPM 自动创建 webhook，无需手动配置
- Stripe：需在 Stripe Dashboard 手动配置 webhook URL（SWPM 设置页面显示）

## 展示的原材料种类（6种）

| 中文名称 | 英文名称 | 缩写 | Category ID | 规格宽度 (mm) |
|---------|---------|------|-------------|--------------|
| 民用镀锌 | Galvanized Steel | GI | 1457211766760558593 | 1000、1200、1219、1250 |
| 镀铝锌 | Galvalume Steel | GL | 1683315093109178369 | 1000、1200 |
| 彩涂 | Pre-painted Galvanized Iron | PPGI | 1482328115005964290 | 1000、1200 |
| 热卷 | Hot Rolled Coil | HRC | 1457211813719986177 | 1010、1500 |
| 轧硬 | Cold Rolled Hard Coil | CRC Hard | 1457211766760558594 | 1000、1200 |
| 光铝 | Aluminum Sheet | AL | 1457211893311098881 | 1000 |

**站点 ID**：1457210664971423746（博兴地区）

---

## 项目结构

### 核心插件
**位置**：`/wp-content/plugins/ippgi-prices/`

**类文件** (`/includes/`)：
- `class-database.php` - 数据库表管理
- `class-api-client.php` - 外部 API 客户端
- `class-cache-manager.php` - 缓存管理
- `class-rest-api.php` - REST API 端点
- `class-scheduler.php` - 定时任务调度
- `class-currency-converter.php` - 货币转换
- `class-historical-importer.php` - 历史数据导入
- `class-current-price-collector.php` - 当前价格采集

### 自定义主题
**位置**：`/wp-content/themes/ippgi/`
**当前版本**：1.10.1

**页面模板** (`/page-templates/`)：
- `page-prices.php` - 价格列表页面
- `page-price-detail.php` - 价格详情页面
- `page-subscribe.php` - 订阅/会员升级页面
- `page-profile.php` - 用户个人资料页面
- `page-favorites.php` - 收藏夹页面
- `page-login.php` - 登录页面
- `page-payment.php` - 支付页面
- `page-invite.php` - 邀请好友页面
- `page-terms.php` - 服务条款页面
- `page-privacy.php` - 隐私政策页面
- `page-about.php` - About Us 页面（可在后台编辑正文）
- `page-contact.php` - 旧联系页面模板（已不再用于导航入口）

**路由说明**：
- 导航与页脚入口统一使用 `/about`

**核心模板**：
- `front-page.php` - 首页模板
- `home.php` - 博客列表页模板
- `single.php` - 文章详情页模板
- `search.php` - 搜索结果页模板

**模板组件** (`/template-parts/`)：
- `price-table.php` - 价格表格组件
- `article-card.php` - 文章卡片组件
- `header-mobile.php` / `header-desktop.php` - 头部
- `footer-nav.php` - 底部导航
- `login-modal.php` - 登录弹窗
- `upgrade-prompt.php` - 升级提示

**功能文件** (`/inc/`)：
- `enqueue.php` - 资源加载
- `customizer.php` - 主题定制器
- `template-functions.php` - 模板函数（含 `ippgi_get_product_dimensions_range()`、`ippgi_format_dimensions_range()`）
- `membership.php` - 会员系统集成
- `announcement.php` - 公告系统

### 首页公告系统

**核心文件**：
- `inc/announcement.php` - 公告 CPT、时间/可见性过滤、用户权限判断
- `template-parts/announcement-banner.php` - 首页公告条模板
- `front-page.php` - 公告条当前插入在首页 `site-main` 顶部

**当前显示方式（2026-04-23 更新）**：
- 公告条当前只在首页显示
- 公告条已改为参与首页正常文档流，不再使用固定悬浮定位覆盖在内容上方
- 公告条当前位于首页 `MyPrices` 卡片上方，并通过轻微负 margin 贴紧 header 下边界分割线

**可见性规则**：
- `All Users (Public)`：所有用户可见
- `Logged-in Users Only`：仅登录用户可见
- `Subscribers Only (Paid Members)`：仅当前 SWPM `Plus(4)` 用户可见

**实现说明**：
- 新增通用 helper：`ippgi_is_paid_member($user_id = null)`
- `ippgi_is_paid_member()` 当前只认 SWPM 等级 `4`，不把 Basic(2) 或 bonus-only 用户视为 paid member
- 因此公告的 `Subscribers Only` 与 `ippgi_user_has_plus()` / `ippgi_is_user_subscribed()` 不同；后两者会把 bonus 有效期用户也算作具备 Plus 访问权限

**补充说明**：
- 仓库内默认插件 `Hello Dolly`（`wp-content/plugins/hello.php`）当前未启用，也不参与任何业务逻辑；如需清理可直接删除

---

## 数据库表结构

**表前缀**：`ippgi_`（注意：不是默认的 `wp_`）

**7 张数据库表**：
1. `ippgi_prices_gi` - 民用镀锌价格表
2. `ippgi_prices_gl` - 镀铝锌价格表
3. `ippgi_prices_ppgi` - 彩涂价格表
4. `ippgi_prices_hrc` - 热卷价格表
5. `ippgi_prices_crc_hard` - 轧硬价格表
6. `ippgi_prices_al` - 光铝价格表
7. `ippgi_prices_exchange_rates` - 汇率表

**表字段**：
- `product_spec` - 产品规格
- `statistics_time` - 价格数据所属日期的凌晨时间（`YYYY-MM-DD 00:00:00`）
- `timestamp` - `statistics_time` 的 Unix 时间戳
- `price_usd` - 美元价格
- `price_usd_min` / `price_usd_max` - 美元价格区间下限/上限（用于午夜快照落库）
- `price_tax_usd` - 美元含税价格
- `price_tax_usd_min` / `price_tax_usd_max` - 美元含税价格区间下限/上限（用于午夜快照落库）
- `exchange_rate` - 汇率（从缓存的价格数据中提取）
- `width` / `thickness` - 宽度/厚度
- `created_at` - 记录创建时间（实际保存时的时间）
- 唯一约束：`(product_spec, statistics_time)`

**当前数据量**（截至 2026-01-26）：

| 表 | 记录数 |
|---|-------|
| GI (民用镀锌) | 38,257 |
| GL (镀铝锌) | 85,634 |
| PPGI (彩涂) | 193,168 |
| HRC (热卷) | 7,310 |
| CRC Hard (轧硬) | 137,240 |
| AL (光铝) | 13,500 |
| **价格总计** | **475,109** |
| 汇率 | 1,479 |

**汇率数据范围**：2021-12-31 ~ 2026-01-23（日级别精度）

---

## 邀请奖励系统

### 功能说明
- 用户邀请好友注册成功后，邀请者获得 **7 天 Plus 会员** 奖励
- 由于使用 PayPal/Stripe 订阅模式，无法修改支付平台的扣款日期
- 因此奖励天数**单独追踪**，在订阅结束后自动生效

### 奖励天数机制

**核心原则**：奖励天数不影响 PayPal/Stripe 扣款，而是在订阅结束后延长访问权限。

| 用户状态 | 获得奖励时的行为 |
|---------|---------------|
| 有活跃付费访问（含已取消但未到期） | 奖励天数累积到 `ippgi_unused_bonus_days`，付费访问结束后自动生效 |
| 正在使用奖励天数 | 直接延长当前奖励到期日期 |
| 无订阅、无奖励访问 | 立即激活奖励访问（不修改 SWPM 会员等级） |

**自动激活触发点**：
1. **订阅到期**：SWPM 降级用户 → `ippgi_on_membership_level_change` 检测到从 Plus 降级 → 自动激活累积的奖励天数
2. **获得新推荐奖励时**：`ippgi_award_referral_bonus` 检测到用户无活跃订阅 → 立即激活

**奖励到期处理**（`ippgi_check_bonus_access_expired`）：
- 如果用户已订阅 → 清除奖励标记，不降级
- 如果有新累积的奖励天数 → 自动续期
- 否则 → 保障为 Basic (2)（仅当当前等级不是 2 时才写库更新）

### 用户 Meta 字段

| Meta Key | 说明 |
|---------|------|
| `ippgi_unused_bonus_days` | 未使用的累积奖励天数 |
| `ippgi_bonus_access_start` | 奖励访问开始时间 |
| `ippgi_bonus_access_end` | 奖励访问到期时间 |
| `ippgi_total_referral_bonus_days` | 历史累计获得的奖励天数 |
| `ippgi_referral_bonuses` | 奖励历史记录数组 |
| `ippgi_referral_count` | 推荐人数 |
| `ippgi_invite_code` | 用户的邀请码 |
| `ippgi_referred_by` | 推荐人的用户 ID |
| `ippgi_registration_referral_processed` | 新用户注册时邀请码是否已处理 |

### Profile 页面订阅状态

| 状态 | 说明 | 显示内容 |
|-----|------|---------|
| `active` | 活跃订阅 | 下次扣款日期 + 取消订阅按钮 |
| `bonus` | 正在使用奖励期 | `Active` + `You are currently using your bonus days.` + `Subscribe` 按钮 |
| `cancelled` | 已取消（未到期） | 订阅结束日期 |
| `terminated` | 已终止（无订阅或已到期） | 订阅按钮 |

**注意**：`bonus` 状态单独展示，不再归类为 `terminated`。当用户处于奖励期（新注册奖励、邀请奖励激活、后台手动加天数后激活）时，Profile 页面会显示奖励期正在使用中。
状态判断规则：统一以 `ippgi_bonus_access_end > 当前时间` 判断奖励期是否 active；`ippgi_unused_bonus_days` 仅作为库存天数。

**当前 UI 状态（2026-04-23 更新）**：
- `/profile` 页面当前已隐藏 `Subscription status` 这一整块状态展示内容，不再在页面中直接显示 `Active / Cancelled / Terminated / bonus` 文案。
- `Subscription information` 区块当前仅保留 `Remaining Bonus Days` 内容，原先状态内容下方的灰色分割线也已移除。
- 取消订阅弹窗 DOM、前端脚本以及 `ippgi_cancel_subscription` AJAX 逻辑仍保留在模板中，供后续重新开放取消订阅入口时直接复用；只是当前页面没有显示触发按钮。

### 工作流程
1. 用户访问 `/invite` 页面获取邀请链接
2. 邀请链接格式：`https://yoursite.com/?ref=xxxxxxxx`
3. 被邀请者点击链接，邀请码保存到 Cookie（30天有效，新的 `?ref=` 会覆盖旧值）
4. 被邀请者通过 SWPM 注册
5. 系统自动奖励邀请者 7 天 Plus 会员（累积或立即激活）

### 相关函数
- `ippgi_get_user_invite_link()` - 生成邀请链接
- `ippgi_save_referral_cookie()` - 保存邀请码到 Cookie（支持覆盖旧邀请码）
- `ippgi_process_referral()` - 处理推荐逻辑（成功后才标记已处理）
- `ippgi_award_referral_bonus()` - 累积或激活奖励天数
- `ippgi_has_active_subscription()` - 检查是否有活跃的 PayPal/Stripe 订阅
- `ippgi_activate_bonus_access()` - 激活奖励访问（只写 bonus 元数据，不改 SWPM 等级）
- `ippgi_check_bonus_access_expired()` - 处理奖励到期（续期或保障为 Basic，避免无效写库）
- `ippgi_get_unused_bonus_days()` - 获取未使用的奖励天数
- `ippgi_get_bonus_access_end_date()` - 获取奖励访问到期日期
- `ippgi_get_user_total_bonus_days()` - 获取历史累计奖励天数
- `ippgi_get_invitation_history()` - 获取邀请历史记录
- `ippgi_mask_email()` - 邮箱脱敏显示（如 `john***@gmail.com`）

### 支付页面 UI 实现
**模板文件**：`/page-templates/page-payment.php`

**页面特点**：
- 不使用 `get_header()` / `get_footer()`，使用自定义 HTML 结构（无站点头部/底部/升级提示）
- 隐藏 WordPress admin bar

**页面结构**：
1. **渐变色头部**
   - 副标题：`Subscribe to Plus`
   - 大号价格：`US$10.00` + `/month`（根据 URL 参数动态显示）

2. **白色卡片区域**
   - **Contact Information**：显示当前用户邮箱（只读）
   - **Payment Method**：单选按钮选择 PayPal 或 Credit Card（默认 PayPal）
   - **SWPM 支付按钮区域**：根据选择的支付方式显示对应的 SWPM 按钮
   - **Terms 文本**：订阅条款 + 链接（Terms & Conditions、Privacy Policy、Contact Us）

**URL 参数**：
- `?plan=monthly` - 月度订阅（默认）
- `?plan=yearly` - 年度订阅

**支付方式切换**：
- JavaScript 监听 radio button change 事件
- 切换 `#paypal-btn` 和 `#stripe-btn` 容器的显示/隐藏
- 选中项添加 `payment-method--selected` 类

**按钮样式定制**（`functions.php`）：
- PayPal SDK 语言：通过 `swpm_generate_paypal_js_sdk_args` 过滤器设置 `locale` 为 WordPress 语言
- Stripe 按钮文字：CSS 伪元素将 "Buy Now" 替换为 "Subscribe"
- Stripe 默认样式：通过 `wp_deregister_style('swpm.stripe.style')` 移除
- 所有按钮统一高度 45px

**CSS 样式**：位于 `/assets/css/components.css`
- `.payment-page-body` - 页面 body（隐藏 admin bar）
- `.payment-header` - 渐变色头部
- `.payment-card` - 白色卡片区域
- `.payment-section` - 信息区块
- `.payment-method` - 支付方式选项
- `.payment-buttons-area` - SWPM 按钮容器
- `.payment-terms` - 条款文本

**注意事项**：
- SWPM 支付按钮由 `[swpm_payment_button id="X"]` shortcode 渲染
- PayPal 按钮由 PayPal SDK 动态生成，不支持程序化点击
- 必须直接显示 SWPM 按钮容器，不能隐藏后模拟点击
- SWPM 创建会员记录时使用 PayPal 邮箱，需确保 SWPM `user_name` 与 WordPress `user_login` 匹配

### 邀请页面 UI 实现
**模板文件**：`/page-templates/page-invite.php`

**页面结构**：
1. **标题区域**
   - 主标题：`Earn rewards for each friend you invite.`
   - 副标题：`Share with your friends and get rewards.`
   - 下方有分割线

2. **邀请链接区域**
   - 标题：`Get Your Exclusive Referral Link! Share it with friends and earn rewards!`（24px 粗体）
   - 邀请链接输入框（只读，宽度自适应内容，带阴影效果）
   - Copy 按钮（点击后显示 Toast 提示 "Copy Success!"）
   - 说明文字 + Contact us 链接（带下划线）
   - 下方有分割线

3. **邀请历史表格**
   - 标题：`Invitation History`
   - 表头：Number | Timestamp | Referred User
   - 表头四周圆角（8px）
   - 内容居中对齐
   - **日期格式**：`M d, Y`（如 `Jan 20, 2026`）

**CSS 样式**：位于 `/assets/css/components.css`
- `.invite-page` - 页面容器
- `.invite-link-section` - 邀请链接区域
- `.invite-link-box` - 链接输入框和按钮容器（垂直布局，居中）
- `.invite-link-box__input` - 输入框（带阴影：`box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4)`）
- `.invite-history` - 历史记录区域
- `.invite-history__table` - 表格样式（`border-collapse: separate`）

### 收藏页面 UI 实现
**模板文件**：`/page-templates/page-favorites.php`

**页面结构**：
1. **标题区域**
   - 主标题：`My Favorites selection of China steel and commodities.`
   - 副标题：`Shortlist your favorite price curve, indices, daily prices.`
   - 下方有分割线

2. **材料类型过滤器**
   - 默认显示 "Product" + 向下箭头
   - 点击弹出底部选择器（白色背景，圆角顶部）
   - 选择器顶部居中显示 "Product" 标题
   - 材料类型列表：PPGI、GI、GL、Aluminum Sheet、CRC Hard、HRC
   - 每项右侧有向右箭头
   - 支持切换选中/取消选中（再次点击同一项恢复默认状态）

3. **收藏列表**
   - 三等分布局：产品名称 | 规格 | 心形按钮
   - 规格显示规则：
     - **PPGI, GI, GL, CRC Hard**：只显示 `厚度*宽度`
     - **HRC, AL**：显示 `厚度*宽度 产品名称`
   - 内容居中显示，文字太长自动换行
   - 心形按钮点击可取消收藏（AJAX 请求）
   - 取消收藏后条目滑出动画移除

**收藏数据格式**：
- 存储在 user meta `ippgi_favorites`
- ID 格式：`type-productSpec`（如 `ppgi-1482328115005964290_1000_0.11_彩涂`）
- `ippgi_get_user_favorites()` 解析 productSpec 并返回结构化数据
- 类型键映射：`crc` → `crc_hard`，`aluminum` → `al`

**CSS 样式**：位于 `/assets/css/components.css`
- `.favorites-page` - 页面容器
- `.favorites-header` - 标题区域
- `.favorites-filter` - 过滤器按钮
- `.favorites-item` - 收藏条目（flex 三等分）
- `.favorites-item__heart` - 心形按钮
- `.material-selector` - 底部弹出选择器
- `.material-selector-backdrop` - 遮罩层

### 编辑个人资料页面 UI 实现
**模板文件**：`/page-templates/page-edit-profile.php`

**页面结构**：
1. **标题**：`Edit Member Profile`
2. **表单字段**：
   - Name（文本输入）
   - Country/Region（点击弹出国家选择器）
   - Company Name（文本输入）
   - Email（只读显示）
   - Mobile Number（电话输入，带验证）
3. **提交按钮**：
   - 默认禁用（灰色），表单有变化时启用（蓝色）
   - 通过 JavaScript 检测表单变化

**手机号验证**：
- Country code 与 Mobile Number 分列显示，和手机号补全弹窗共用同一份国家码表及服务端规范化规则
- 已有国际号码优先根据前缀拆分；共享国家码优先结合资料中的 Country/Region 判断；旧号码没有前缀时再按 Country/Region 预选
- 本地号码允许数字、空格、连字符、括号；国家码和本地号码合计最少 6 位、最多 15 位数字
- 保存格式统一为 `+国家码 本地号码纯数字`；手机号国家码不会自动修改资料中的 Country/Region
- 输入时自动过滤非法字符，失焦时验证，无效显示红色错误提示

**价格入口手机号补全**：
- 已登录且用户 meta `phone` 为空时，从首页等价格入口继续会显示可关闭的手机号弹窗；直接访问 `/prices` 仍按“登录即可访问”处理，不增加手机号硬拦截
- 弹窗将国家/地区代码与本地号码分开填写；优先按用户资料中的 `country` 预选，资料为空或无法识别时保持“请选择”，不按 IP 或界面语言推断
- 服务端按受支持的国家码表校验并统一保存为 `+国家码 本地号码纯数字`，继续复用 user meta `phone`，当前不做短信验证

### SEO Meta 自动生成与人工覆盖
**实现文件**：`/inc/seo.php`（由 `functions.php` 统一加载）

- 所有前台文章、页面、归档、搜索和 404 请求统一输出 `<meta name="description">` 与 `<meta name="keywords">`；以后新增的文章/页面无需修改模板即可自动获得 Meta。
- 文章/页面编辑器增加 `SEO Metadata` 面板，人工值保存到 `_ippgi_seo_description`、`_ippgi_seo_keywords`。人工值优先；留空时 Description 依次使用页面专用默认值、摘要、正文、标题兜底，Keywords 使用标题、文章分类/标签及站点业务词并自动去重。
- Description 最长 160 字符；Keywords 最多保留 12 项。`meta keywords` 仅为兼容需求保留，不应把它当作主要搜索排名信号。
- `/login`、`/membership-login`、`/profile`、`/edit-profile`、`/favorites`、`/invite`、`/payment`、支付结果页、`/prices`、`/price-detail`、搜索页和 404 输出 `noindex, noarchive`。
- TranslatePress SEO Pack 负责 Description 与 hreflang 的多语言处理，主题通过 `trp_node_accessors` 额外注册 Keywords 的 `content` 属性翻译。
- 若未来启用 Yoast、Rank Math、SEOPress 或 AIOSEO，主题会停止自身 Meta 输出，避免重复 Description/Keywords。

**国家选择器**：
- 点击 Country/Region 字段弹出模态框
- 支持搜索过滤
- 包含完整国家列表

### Toast 提示组件
**模板文件**：`/template-parts/toast.php`

**通用提示组件**，固定在屏幕中央，不占用文档流。全局可用，任何页面都可以调用。

**使用方法**：
```javascript
// 显示成功提示
ippgiToast.success('操作成功');

// 显示错误提示
ippgiToast.error('操作失败');

// 自定义类型和持续时间（毫秒）
ippgiToast.show('消息内容', 'success', 5000);
```

**加载方式**：
- 使用 `get_footer()` 的页面：自动加载（在 footer.php 中引入）
- 不使用 `get_footer()` 的页面：需手动引入 `get_template_part('template-parts/toast')`

**HTML 结构**：
```html
<div class="toast-message toast-message--success" id="ippgi-toast">
    <span class="toast-message__text">提示内容</span>
    <span class="toast-message__icon toast-message__icon--success">
        <svg><!-- 打钩/叉号图标 --></svg>
    </span>
</div>
```

**样式特点**：
- 固定定位在屏幕正中央
- 宽度 60%，最大宽度 300px
- 淡绿色背景（成功 #e8f5e9）/ 淡红色背景（错误 #ffebee）
- 黑色边框（1px #333）
- 绿色/红色文字（18px，font-weight: 600）
- 右侧圆形图标（绿色打钩/红色叉号）
- 默认 3 秒后自动淡出消失

**CSS 类**：
- `.toast-message` - 基础样式
- `.toast-message--success` - 成功状态
- `.toast-message--error` - 错误状态
- `.toast-message__text` - 文字内容
- `.toast-message__icon` - 图标容器
- `.toast-message__icon--success` - 绿色圆形打钩
- `.toast-message__icon--error` - 红色圆形叉号

**JavaScript API**：
- `ippgiToast.show(message, type, duration)` - 显示提示
- `ippgiToast.success(message, duration)` - 显示成功提示
- `ippgiToast.error(message, duration)` - 显示错误提示
- `ippgiToast.hide()` - 手动隐藏提示

### 升级提示 Banner
**模板文件**：`/template-parts/upgrade-prompt.php`

**浮动提示组件**，固定在屏幕右下角，提示用户升级到 Plus 会员。

**显示逻辑**（定义在 `footer.php`）：
```php
if (is_user_logged_in() && !ippgi_is_user_subscribed() && !is_page('subscribe')) {
    get_template_part('template-parts/upgrade-prompt');
}
```

**显示条件**：

| 用户状态 | 是否显示 |
|---------|---------|
| 未登录（Guest） | ❌ 不显示 |
| Basic 会员（Level 2） | ✅ 显示 |
| Bonus 访问中 | ✅ 显示（有 Plus 权限但建议订阅） |
| Plus 会员（Level 4） | ❌ 不显示 |
| 订阅页面（subscribe） | ❌ 不显示 |

**说明**：`ippgi_is_user_subscribed()` 函数只检查 Plus 会员（有活跃订阅），Bonus 访问用户不被视为"已订阅"，因此会显示升级提示。订阅页面本身已包含订阅相关内容，无需重复显示升级提示。

**功能**：
- 点击 × 按钮可关闭
- 点击 "Upgrade" 跳转到订阅页面

### 价格列表页面 (Prices Page)
**模板文件**：`/page-templates/page-prices.php`

**当前访问规则**：
- 未登录用户访问 `/prices`：跳转登录页，并携带 `redirect_to`
- 已登录用户访问 `/prices`：允许直接进入，不再要求 Plus 或 bonus
- 首页价格表、Read More、Footer 产品价格链接、导航 `Prices & Trends` 跳转逻辑已统一为与直链访问 `/prices` 一致
- 价格详情页 `/price-detail` 同样为登录即可访问，不再要求 Plus 或 bonus 权限

**页面结构**：
1. **页面标题**
   - 固定标题：`Price charts and tables of China steel and commodities`
   - 副标题：`Prices are quoted on an ex works (EXW) basis in China and exclude freight costs.`

2. **产品选择器**
   - 下拉按钮显示 "Product" + 向下箭头
   - 点击展开下拉菜单，显示所有产品类型（PPGI、GI、GL、HRC、CRC Hard、Aluminum）
   - 选中项带勾号标记

3. **选中产品展示**
   - 居中显示当前选中的产品名称（如 "PPGI"）
   - 两侧带有装饰线

4. **Key Attributes**
   - 标题：`Key attributes`（粗体）
   - 动态内容：从缓存数据中获取厚度范围和宽度范围
   - 使用 `ippgi_get_product_dimensions_range()` 和 `ippgi_format_dimensions_range()` 函数

5. **宽度筛选标签**
   - 水平滚动的药丸式标签（如 1000mm、1200mm）
   - 宽度选项从缓存数据动态获取，不再硬编码
   - 点击标签本地切换数据（无需网络请求）

6. **更新时间**
   - 显示更新时间（如 `Updated: Jan 30, 2026, 10:00 AM (UTC+8)`）
   - 价格列表页顶部含税切换按钮已移除
   - 表格默认显示含税价格（`showTaxInclusive = true`）

7. **价格表格**
   - 表头：Dimensions(mm) | Latest($) | Change($) | Historical
   - `Latest($)` 显示美元区间值而非单点值；区间由每次价格列表刷新时统一生成的一组全局随机上下浮动因子计算
   - 首页与 `/prices` 页当前都默认按含税美元区间显示，Latest 颜色也统一按含税区间均价与上一轮含税区间均价比较决定
   - 蓝色表头背景（#e2f5fb）
   - Dimensions 列显示规则：
      - **PPGI, GI, GL, CRC Hard**：只显示 `厚度*宽度`（如 `0.4*1200`）
      - **HRC, AL**：显示 `厚度*宽度 产品名称`（如 `2.0*1010 热卷`）
   - Change 列根据涨跌显示不同颜色
   - Historical 列显示 "Trend >" 按钮，跳转到价格详情页

8. **Disclaimer**
   - 固定免责声明文本
   - 原表格下方带锁升级提示（`premium-gate`）已删除

**URL 参数支持**：
- `?type=ppgi` - 直接指定产品类型（小写）
- `?category=PPGI` - 从首页跳转时使用（大写，自动映射）
- `?width=1000` - 指定默认宽度

**数据来源**：
- 价格列表缓存已按分类拆分存储（如 `ippgi_prices_price_list_category_ppgi`），服务端按需拼装完整响应
- 通过 `window.ippgiPricesPage` 传递给 JavaScript
- 宽度切换在本地完成，无需 API 调用

**CSS 样式**：位于 `/assets/css/components.css`
- `.prices-page-header` - 页面标题区域
- `.product-selector` - 产品下拉选择器
- `.selected-product` - 选中产品展示
- `.key-attributes` - 属性描述区域
- `.width-filter` - 宽度筛选标签
- `.price-controls` - 更新时间和含税切换
- `.prices-table` - 价格表格
- `.prices-disclaimer` - 免责声明

### 价格详情页面 (Price Detail Page)
**模板文件**：`/page-templates/page-price-detail.php`
**WordPress 页面**：`/price-detail/`（需在后台创建页面并选择模板）

**页面结构**：
1. **页面标题**
   - 动态标题：`Price charts and tables of China {产品代码} and commodities`
   - 副标题/免责声明

2. **产品信息表格**
   - 蓝色表头（#e7f5fb）：Product | Dimensions(mm) | Favorite
   - 数据行：产品代码（粗体） | 规格 | 心形收藏按钮
   - 规格显示规则：
     - **PPGI, GI, GL, CRC Hard**：只显示 `厚度*宽度`
     - **HRC, AL**：显示 `厚度*宽度 产品名称`
   - 带边框样式

3. **收藏功能**
   - 心形按钮：已收藏显示红色实心，未收藏显示空心轮廓
   - 点击切换收藏状态（AJAX 请求）
   - 操作后显示 Toast 提示（2秒）
   - 收藏 ID 格式：`type-productSpec`（如 `ppgi-1482328115005964290_1000_0.11_彩涂`）

4. **实时数据区域**
   - 标题：`Real-Time Price`
   - 不再请求单规格 `/price` 实时接口，也不再显示含税切换按钮
   - 默认直接显示该规格在价格列表缓存中的**含税美元价格区间**（优先读取 `lastpriceTax_range_min_usd` / `lastpriceTax_range_max_usd`，回退到单点税价字段）
   - 不再显示涨跌值、涨跌百分比、Avg、WoW、MoM、YoY
   - 实时价显示格式统一为 `$lower~$upper`，保留两位小数

5. **价格图表区域**
   - **日期范围选择器**：日历图标 + "Start Date ~ End Date"，点击打开底部日期选择器
   - 时间范围标签：`7D | 15D | 1M`（带滑动切换动画）
   - 图表标题：`{产品代码} Dimensions(mm):{规格}`
   - 预设标签和自定义区间均统一从本地数据库 `/historical` API 获取历史数据；价格详情页前端已不再使用 `/statistics`
   - 日期选择器已禁止“单日范围”确认，避免回退到旧的单日统计接口
   - 图表改为**两条含税美元区间线**：下轨使用 `price_tax_usd_min`，上轨使用 `price_tax_usd_max`
   - 两条线之间带淡色填充区域，触摸十字线时信息框显示 `$lower~$upper`
   - 原最高点/最低点气球标签已隐藏
   - **X 轴标签**：统一显示日期（MM-DD 格式），并按当前数据范围均匀抽样
   - **数据降采样**：数据量超过 300 点时自动降采样，保证图表流畅显示

6. **日期选择器（底部弹出）**
   - 与博客页面相同的底部弹出式日期选择器
   - 支持选择开始日期和结束日期
   - 日历导航（上一月/下一月）
   - 禁止选择未来日期
   - 禁止确认同一天的单日范围
   - Clear 按钮清空选择，Confirm 按钮确认并加载数据
   - 选择自定义日期范围后，预设标签（7D/15D/1M）取消激活状态
   - 点击预设标签时，日期选择器文本重置为 "Start Date ~ End Date"

7. **询价卡片**
   - 新增内嵌 `Request a Quote` 卡片，位置在图表下方、Disclaimer 上方
   - 复用公共模板 `template-parts/quote-request-form.php`
   - 当前不再自动预填任何字段；首页弹窗与价格详情页内嵌卡片打开时均默认为空表单
   - 描述文案使用单个段落加 `<br>` 手动换行，避免段落间距过大

8. **Disclaimer**
   - 使用与价格列表页一致的整段免责声明文案与样式

**URL 参数**：
- `?type=ppgi&spec=categoryId_width_thickness_材料名称` - 从价格列表页跳转
- `?material=gi` - 旧版兼容

**访问控制与容错**：
- `/price-detail` 当前为“登录即可访问”
- 页面会先执行 `ippgi_normalize_product_type()`；如果产品类型不存在或当前被标记为不可见（如已隐藏的 HRC），直接返回 404，而不是回退显示默认产品

**数据来源**：
- 单规格实时价格 REST API `/wp-json/ippgi-prices/v1/price` 已停用；如收到请求，服务端直接返回错误，不再向上游转发

**CSS 样式**：位于 `/assets/css/components.css`
- `.detail-product-info` / `.detail-product-table` - 产品信息表格
- `.detail-realtime` - 实时数据区域
- `.detail-chart-section` - 图表区域
- `.detail-chart__range-tabs` - 时间范围标签
- `.quote-card` / `.quote-form` - 价格详情页内嵌询价卡片

### 询价表单（Quote Request）

**公共模板**：
- `template-parts/quote-request-form.php` - 询价表单主体
- `template-parts/quote-request-modal.php` - 首页弹窗容器

**当前入口**：
1. 首页价格区 `Read More` 下方文案 `Looking for a lower price or customized specs? Get an Official Quote.` 点击后打开询价弹窗
2. 价格详情页图表下方直接展示内嵌询价卡片（非弹窗）

**表单字段**：
- 必填：`Name`、`Email / WhatsApp`、`Company`、`Steel Product of Interest`
- 选填：`Additional Details (Optional)`
- 当前行为：所有字段默认留空，不再根据登录用户信息或当前产品规格自动填入

**提交逻辑**：
- 首页弹窗与价格详情页内嵌卡片共用同一套前端提交逻辑（`assets/js/main.js`）
- 后端统一走 `ippgi_submit_quote_request` AJAX action
- `source` 当前支持：
  - `homepage`
  - `price_detail`

**校验说明**：
- 前端仍使用浏览器原生 `required` 校验提示，提示语言取决于用户浏览器/系统语言，而不是站点语言
- 输入框文字颜色、标题颜色、描述颜色当前统一使用 `var(--color-text-primary)`（`#333333`）

---

## REST API 接口

### 公开接口

| 端点 | 方法 | 说明 |
|------|------|------|
| `/wp-json/ippgi-prices/v1/prices` | GET | 获取所有材料价格列表 |
| `/wp-json/ippgi-prices/v1/price` | GET | 已停用，返回错误 |
| `/wp-json/ippgi-prices/v1/statistics` | GET | 获取价格历史统计（用于 TD 图表，从外部 API） |
| `/wp-json/ippgi-prices/v1/historical` | GET | 获取历史价格数据（用于 1M-4Y 图表，从本地数据库） |

**实时价格接口** (`/price`)：
- 该接口已停用；如收到请求，服务端直接返回错误，不再向上游转发。

**数据流程**：
1. 客户端发送 `productSpec`、`categoryId`（默认不传 `date`）
2. 服务器按业务日期规则生成请求日期，并添加 `siteId`
3. 检查缓存（最新价缓存键：`md5(productSpec)`）
4. 缓存未命中则转发请求到 `www.rendui.com/api`
5. 响应数据自动进行货币转换（CNY → USD）
6. 转换后数据缓存并返回

**统计数据接口参数** (`/statistics`)：
| 参数 | 必填 | 说明 |
|------|------|------|
| `productSpec` | 是 | 完整产品规格 |
| `categoryId` | 是 | 分类 ID |
| `from` | 是 | 开始时间（YYYY-MM-DD HH:MM:SS 格式） |
| `to` | 是 | 结束时间（YYYY-MM-DD HH:MM:SS 格式） |

**统计数据流程**：
1. 客户端发送 `productSpec`、`categoryId`、`from`、`to`
2. 服务器生成缓存键：`ippgi_stats_` + `md5(productSpec + '_' + from + '_' + to)`
3. 缓存命中直接返回（带 `cached: true` 标识）
4. 缓存未命中则添加 `siteId` 和 `phone` 头转发到 `www.rendui.com/api/v1/jec/rendui/prices/statistics`
5. 响应数据自动进行货币转换（CNY → USD），包括 `list` 数组和 `rangeAvgPrice`
6. 转换后数据缓存 1 小时（3600 秒）并返回

**历史数据接口参数** (`/historical`)：
| 参数 | 必填 | 说明 |
|------|------|------|
| `productSpec` | 是 | 完整产品规格 |
| `category` | 是 | 产品分类名称（GI、GL、PPGI、HRC、CRC Hard、AL） |
| `range` | 否* | 预设时间范围（1m、6m、1y、2y、3y、4y） |
| `from` | 否* | 自定义开始日期（YYYY-MM-DD 格式） |
| `to` | 否* | 自定义结束日期（YYYY-MM-DD 格式） |

*注：`range` 或 `from`+`to` 二选一必填

**历史数据流程**：
1. 客户端发送 `productSpec`、`category`，以及 `range` 或 `from`+`to`
2. **同一天检测**：如果 `from` 和 `to` 是同一天，转发到外部 statistics API（09:00-17:00 时间范围）
3. 服务器计算日期范围：
   - 预设模式：根据 range 计算（如 4y = 4 年前的今天到今天）
   - 自定义模式：使用 from 和 to 参数（to 不能超过今天）
4. 生成缓存键：
   - 预设模式：`ippgi_hist_` + `md5(productSpec + '_' + category + '_' + range)`
   - 自定义模式：`ippgi_hist_` + `md5(productSpec + '_' + category + '_' + from + '_' + to)`
5. 缓存命中直接返回
6. 缓存未命中则查询本地数据库表（`ippgi_prices_{category}`）
7. **日期填充**：遍历日期范围内的每一天
   - 有数据的日期：使用实际价格
   - 无数据但在首条数据之后（周末/节假日）：沿用前一天价格
   - 无数据且在首条数据之前（产品不存在时期）：价格为 0
8. 转换后数据缓存 1 小时并返回

### 管理员接口

| 端点 | 方法 | 说明 |
|------|------|------|
| `/wp-json/ippgi-prices/v1/cache-stats` | GET | 缓存统计 |
| `/wp-json/ippgi-prices/v1/clear-cache` | POST | 清除缓存 |
| `/wp-json/ippgi-prices/v1/manual-update` | POST | 手动触发更新 |

---

## 定时任务系统

**时区**：所有时间均为北京时间（Asia/Shanghai，UTC+8）

### 凌晨 00:10 任务流程（北京时间）
1. **保存昨日数据**
   - 读取缓存的价格列表（通常为昨日 17:00 的数据）
   - 直接按缓存中的原始价格口径保存历史价格快照到各材料表
   - 从缓存价格列表中提取汇率并保存到 `ippgi_prices_exchange_rates` 表
   - 不刷新最新汇率
   - 不重算价格列表缓存中的美元字段
   - 不重算已缓存单规格详情中的美元字段
   - 若缓存缺失，允许回源抓取最新价格列表作为兜底

**注意**：00:10 任务不会主动刷新最新汇率，也不会统一重算美元价格。缓存中仍保留上一版商品行情，供 00:10-09:10 期间使用；仅在缓存缺失时允许回源抓取最新价格列表兜底。

### 01:10-08:10、18:10-23:10 每小时汇率刷新（北京时间，共14次）
该批 off-hours 汇率刷新任务已移除，当前不会在这些时段自动刷新汇率，也不会自动重算缓存中的 USD 值。

### 09:10-17:10 每小时更新（北京时间，共9次）
1. 清除实时价格缓存和汇率缓存，保留价格列表缓存
2. 刷新汇率缓存（Aliyun）
3. 生成一组“本次刷新共用”的随机区间因子（下限 `0.1%~0.5%`，上限 `1%~2%`）
4. 从外部 API 逐个分类获取最新价格数据；成功的分类会立刻按最新汇率转换为 USD
5. 若某分类获取失败，则直接保留该分类的旧 USD 缓存，不再按最新汇率重算
6. 对本次最终结果中的全部分类统一重算 `Latest($)` 区间上下限，再整体写回价格列表缓存

### 时区实现说明
- 使用 `wp_timezone()` 和 `DateTime` 对象计算正确的 Unix 时间戳
- 避免使用 `current_time('timestamp')`（返回伪时间戳，会导致时区计算错误）
- 相关代码位于 `/wp-content/plugins/ippgi-prices/includes/class-scheduler.php`

---

## 客户端请求日期逻辑

**适用页面**：首页价格表、价格详情页

**逻辑说明**：
- 客户端请求不再携带 `date` 参数
- 首页优先使用 `sessionStorage` 的短时缓存（10 分钟）减少重复请求

**原因**：
- 日期规则统一由服务端维护，避免前后端重复逻辑
- 由定时任务控制“最新数据”刷新时机（09:10-17:10 每小时）
- 减少页面来回切换时的重复加载和等待

**实现位置**：
- 首页：`/assets/js/main.js` 单次请求 `/prices`（无 `date`），并将结果写入 `sessionStorage`
- 价格详情页：不再请求 `/price`，单规格实时价格链路已停用

**服务端支持**：
- 服务端业务日期函数：北京时间 9:00 前用昨天，9:00 及之后用今天
- 价格列表缓存键不含日期；按分类拆分为 `ippgi_prices_price_list_category_{category}`，元数据键为 `ippgi_prices_price_list_meta`
- 价格详情“最新价”缓存键不含日期（`md5(productSpec)`）
- `/prices/category` 仍保留可选 `date` 参数，便于运维/调试时覆盖默认日期；`/price` 已停用

---

## 外部 API 集成

### 价格数据 API
- **价格列表**：`GET https://www.rendui.com/api/v1/jec/rendui/prices/daily`
  - 不传自定义请求头
- **实时价格**：`daily/getByProductSpecAndDate` 已停用，服务端不再请求该接口
- **历史数据**：`GET https://www.rendui.com/api/v1/jec/rendui/prices/statistics`
  - 请求头：`phone: 13792171909`

### 汇率数据 API
- **服务商**：阿里云市场（数脉 API）
- **鉴权方式**：仅签名认证（APP Key + APP Secret，`X-Ca-*` 签名头），不再使用 APPCODE 回退
- **当前汇率**：`GET https://tysjhlcx.market.alicloudapi.com/exchange_rate/convert`
  - 参数：`fromCode=USD&toCode=CNY&money=1`
- **历史汇率**：`GET https://tysjhlcx.market.alicloudapi.com/exchange_rate/history`
  - 参数：`code=USD` + `startDate/endDate(yyyyMMdd)` 或 `month(yyyyMM)`
- **配置项**：
  - `IPPGI_ALIYUN_APP_KEY`、`IPPGI_ALIYUN_APP_SECRET`
  - 建议在环境变量中注入，`wp-config.php` 读取 env 值
  - 也可在后台配置：`外观 > 自定义 > IPPGI Settings > API Credentials`（保存到 WordPress options）
  - 凭证读取优先级：常量（`wp-config.php`） > WordPress option > 系统环境变量

### 邮件发送 API (Gmail API)
- **服务商**：Google Cloud Platform (Gmail API)
- **实现方式**：WP Mail SMTP 插件 + OAuth 2.0 (Client ID / Client Secret)
- **发件账号**：wlg2008g@gmail.com
- **作用域**：`https://www.googleapis.com/auth/gmail.send`
- **重定向 URI**：`https://{domain}/wp-admin/options-general.php?page=wp-mail-smtp&action=setup`

**部署注意**：
- 代码同步（git pull）只会带上“后台设置页功能”，不会带上本地数据库中的凭证值
- 线上测试服需要重新在后台填写一次凭证，或用 `wp option update` 写入
- Gmail API 必须在 Google Cloud Console 中将 Publishing Status 设置为 "In Production" 以获得永久授权。

---

## 国际化 / 多语言（TranslatePress）

### 插件与版本
- `translatepress-multilingual` —— TP 主插件
- `translatepress-developer` —— Pro Developer 外壳，含付费 add-ons：
  - `automatic-language-detection`
  - `browse-as-other-roles`
  - `deepl`
  - `multiple-domains`（暂未启用，子目录模式不需要）
  - `navigation-based-on-language`
  - `translator-accounts`

### URL 结构（子目录模式）
- 默认语言英文走根路径，**Use a subdirectory for the default language = No**
- `/fr/...` `/ru/...` `/es/...` 三个翻译语种走子目录
- TP 自动改写所有内链；切换器 `<a>` 用 `url_converter->get_url_for_language($code)` 生成

### 自动翻译引擎
- **Google Translate v2**（不用 TP 自带 AI——Developer 版授权下 AI 配额为 0；DeepL 也可，作为备选）
- TP 设置：`Settings → TranslatePress → Automatic Translation`
- 重要选项：
  - `Block Crawlers from Translating: Yes`（避免爬虫触发翻译消耗配额）
  - `Characters per day` 设保守值兜底
- 短词无上下文时机翻常常错（如 `Trend` → `S'orienter`、`May` → `Peut`/`номер`/`Puede`），需要在 TP gettext 表里手动校正

### 自定义语言切换器
**位置**：`header.php:35-78` —— top-bar 区域（移动端 / 桌面端共用同一段）

**实现方式**：
- 用 `class_exists('TRP_Translate_Press')` 兜底
- 从 `$trp_settings['publish-languages']` 读已发布语言
- 从 `$GLOBALS['TRP_LANGUAGE']` 读当前语言
- 内置 4 种语言映射数组（语言代码 → 国旗 SVG + 标签）
- ≥2 种语言时才渲染切换器；当前语言用 `is-current` 类高亮

**国旗资源**：
- `assets/images/flag-uk.svg` `flag-fr.svg` `flag-es.svg` `flag-ru.svg`
- 矩形 SVG，60×30 viewBox，前端展示 20×14 + 圆角 2px

**CSS**：`assets/css/layout.css` `.language-selector` `.language-selector__menu` 一套样式

**JS**：`main.js` 里的 `initLanguageSwitcher()`，复用产品下拉那套 `aria-expanded` + 外点关闭模式

**z-index 注意**：`.top-bar` 用 `calc(var(--z-sticky) + 1)`（=201），下拉菜单才能覆盖在 `.site-header`（200）之上，否则下拉的第一个选项会被站点 header 挡住。

### JS 字符串字典（`ippgiData.strings`）

**问题**：TP 的 HTML 输出层翻译只解析可见文本节点，**会跳过 `<script>` 标签内的 JSON**。所以 JS 里通过 `ippgiData.strings.xxx` 读到的字符串必须**服务端预翻译**好。

**实现位置**：`inc/template-functions.php` 中的 `ippgi_get_js_i18n_strings()`

**翻译查找顺序**（避免双重翻译）：
1. `__($english, 'ippgi')` —— 命中 TP gettext（用户在 `Settings → TP → Translate Strings → Gettext` 改的）
2. 没改过 → `trp_translate($english, null, false)` —— 走 TP 常规字符串 / Google 自动翻译

**返回值清洗**（关键）：
所有返回值都经过 `$sanitize` 闭包：
- 剥掉 `<translate-press data-trp-translate-id="..."> ... </translate-press>` 编辑器包裹标签
- `html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')` —— 把 `S&#039;orienter` 还原成 `S'orienter`，否则 JS `escapeHtml()` 会二次编码成 `S&amp;#039;orienter`

**缓存机制**：
- 缓存键：`ippgi_js_i18n_v7_<md5(language_code)>`
- TTL：12 小时
- 默认语言（en_US）跳过缓存（直接返回原文）
- 请求内 static memo 防止同一次请求多次读 transient
- 修改函数逻辑时，把当前版本段（现为 `v7`）升一位即可让旧缓存失效

**自动失效 hook**：
- `trp_save_editor_translations_regular_strings`
- `trp_save_editor_translations_gettext_strings`
- `trp_machine_translated_strings`
- 触发时调 `ippgi_invalidate_js_i18n_cache()`，DELETE `_transient_ippgi_js_i18n_*`

**手动清缓存**（admin 在 Settings → Translate Strings 页面改翻译时上面的 hook **不会**触发）：
```bash
cd /home/html/www/ippgi
wp transient delete --all --allow-root
```

**字典字段清单**（截至 v7）：
`loading / loadingPrices / error / copied / added / removed / favoriteAddedFull / favoriteRemovedFull / submitting / savingPhone / countryCodeRequired / phoneRequired / invalidPhone / phoneSaveFailed / noPriceData / noPriceDataWidth / failedLoadPrices / updatedLabel / timezoneSuffix / trend / startDateEndDate / thProducts / thDimensions / thLatest / months[12]`

新增字段时：
1. 在 `ippgi_get_js_i18n_strings()` 加一行 `'<key>' => $tr('<English source>')`
2. JS 里用 `t('<key>', '<English fallback>')` 读取（`t()` 是 `main.js` 顶部 IIFE 作用域里的辅助函数）
3. 升缓存版本

### 关键 BUG 与坑（务必看一遍再改 i18n 相关代码）

**1. TP gettext 默认在 `wp_head` 优先级 100 才挂载——晚于 `wp_enqueue_scripts`**

`functions.php` 必须有：
```php
add_filter('trp_apply_gettext_early', '__return_true');
```
否则 `wp_localize_script` 阶段调用的 `__('xxx', 'ippgi')` 全部返回原文（gettext 还没被 TP 接管），后果是字典被打 fallback 走机翻、翻译质量下降。

**2. 双重翻译陷阱**

不要写 `$tr(__('May', 'ippgi'))`：先 `__()` 拿到译文 `Mai`，再被 `trp_translate` 当英文翻译一次，结果是无意义乱翻（`Peut`/`номер`/`Puede` 都是这么来的）。

`$tr` 的输入必须是**英文原文**，由 `$tr` 内部决定走 gettext 还是 trp_translate。

**3. `trp_translate()` 返回的字符串可能带编辑器包裹标签或 HTML 实体**

- 编辑器会话期间调用可能返回 `<translate-press data-trp-translate-id="421">Peut</translate-press>`，JSON 化后 JS `textContent =` 会把标签作为文字显示
- 撇号会变成 `&#039;`，再经 JS `escapeHtml()` 变成 `&amp;#039;`，浏览器还原成可见 `&#039;`

`$sanitize` 闭包把这两种污染一次性处理掉。**gettext 路径也要走 sanitize**（早期版本只在 `trp_translate` 路径处理，导致 gettext 译文中的撇号一直显示编码）。

**4. 客户端 DOM 渲染 vs 服务端 PHP 渲染冲突**

`home.php:132` 的日期选择器月份**之前**用 `<?php echo esc_html(date_i18n('F Y')); ?>`：
- WordPress 没有 ippgi 主题域的俄语 .mo，PHP 输出 `May 2026`
- TP HTML 输出层把 `May` 按"常规字符串"查表翻译成 `номер`（之前机翻的烂值入库了）
- JS `renderCalendar()` 应该在打开选择器时覆盖，但若 JS 没及时跑完用户就看到 `номер`

修复：`home.php` 里把 PHP 渲染改为空 `<span data-no-translation></span>`，`main.js` 的 `initDatePicker()` 末尾加 `renderCalendar()` 立即调用，让 JS 一开始就用 `ippgiData.strings.months` 填好。

**5. 浏览器 HTML 缓存**

服务端清缓存 + 重新生成翻译后，必须**硬刷新**（`Cmd+Shift+R` / `Ctrl+Shift+F5`）才能拿到新 HTML。普通刷新会用浏览器缓存的旧 HTML（含旧 `ippgiData`）。这是 90% "看起来没生效" 的原因。

### TP gettext 数据库表

| 表 | 用途 |
|---|------|
| `ippgi_trp_gettext_original_strings` | 主题/插件 `__()` 调用的源字符串 + textdomain 总表 |
| `ippgi_trp_gettext_<lang>` | 各语言的 gettext 译文（如 `ippgi_trp_gettext_fr_fr`） |
| `ippgi_trp_dictionary_en_us_<lang>` | 各语言的"常规字符串"译文（HTML 文本节点） |
| `ippgi_trp_original_strings` / `_meta` | 常规字符串总表 |
| `ippgi_trp_slug_originals` / `_translations` | URL slug 翻译（当前未启用 slug 翻译） |

直接查 gettext 译文：
```sql
SELECT id, original, translated, domain
FROM ippgi_trp_gettext_fr_fr
WHERE original = 'May' AND domain = 'ippgi';
```

### 编辑翻译的两条路径

| 入口 | 存哪 | 适用 |
|------|------|------|
| 前台 **Translate Page** 可视化编辑器 | 常规字符串表 / gettext 表（视点击的内容而定） | 所见即所得，最直观 |
| 后台 **Settings → Translate Strings** | 常规字符串表 + Gettext 表分开管理 | 批量操作、按 textdomain 过滤 |

我们的 JS 字典 (`__('xxx', 'ippgi')`) 的标准编辑入口是 **Settings → Translate Strings → Gettext → 搜源词 → 找 `domain=ippgi` 那行 → 改对应语言列**。

### 已校对的高质量术语（gettext 用）

| 英文 | 法语 | 俄语 | 西语 |
|------|------|------|------|
| May | Mai | Май | Mayo |
| Trend | Tendance | Тренд | Tendencia |
| Latest($) | Dernier ($) | Последний ($) | Último ($) |
| Updated: | Mis à jour : | Обновлено: | Actualizado: |

> 短词机翻经常翻不准（`Trend → S'orienter`、`Latest → Dernière version` 等），UI 类术语必须人工核对。

### 注意：邮件不在 TP 翻译范围

TP 只翻前端 HTML，**不翻邮件**。SWPM 注册欢迎、订阅升级 / 取消通知等邮件需要单独维护多语言模板（或全部统一英文发送）。当前策略待定。

---

## 开发进度

### Phase 1 - 已完成 ✅

#### 1. 核心插件开发 ✅
- 数据库表管理、API 客户端、缓存管理、REST API、定时任务、货币转换、历史数据导入

#### 2. 历史数据导入 ✅
- 价格数据：475,109 条（2022-2026年）
- 汇率数据：1,479 条（日级别精度）

#### 3. 定时任务系统 ✅
- 凌晨保存数据、工作时间每小时更新

#### 4. 主题开发 ✅
- 响应式设计（移动端优先）
- 首页、价格列表、价格详情、订阅、个人资料、收藏夹、登录、支付、邀请
- 博客列表、文章详情、搜索结果
- 服务条款、隐私政策、联系我们

#### 5. 会员系统集成 ✅
- Simple Membership Plugin 集成
- 会员等级权限控制
- 邀请奖励系统（7天 Plus 会员）

#### 6. 首页功能 ✅
- 价格表无限循环左右滑动轮播（5秒间隔，带指示点）、Banner 轮播、Market Insights

#### 7. 博客功能 ✅
- 博客列表页（home.php）
- 日期范围筛选
- 搜索功能

#### 8. 定时任务时区修复 ✅
- 修复 WP-Cron 定时任务时区问题，使用 `wp_timezone()` 和 `DateTime` 正确计算北京时间
- 午夜数据保存：北京时间 00:10
- 每小时刷新：北京时间 09:10-17:10

#### 9. 价格列表页面重新设计 ✅
- 根据 Figma 设计稿完全重写 `/prices/` 页面
- 产品下拉选择器、宽度筛选标签、价格表格
- 动态 Key Attributes（厚度/宽度范围从缓存获取）
- 支持 `?type=` 和 `?category=` URL 参数
- 本地数据切换（无需 API 调用）

#### 10. 价格详情页面重新设计 ✅
- 根据 Figma 设计稿完全重写 `/price-detail/` 页面
- 产品信息表格、实时数据区域、图表区域占位
- 从缓存获取当前价格和涨跌数据
- 时间范围标签（TD、1M、6M、1Y、2Y、3Y、4Y）

#### 11. 实时价格 API 重构 ✅
- 重构数据流：客户端 → 服务器 REST API → 缓存检查 → www.rendui.com/api
- “最新价”缓存键改为仅包含 `productSpec`（`md5(productSpec)`）
- 自动货币转换（CNY → USD），支持含税/不含税价格
- 处理 API 响应边缘情况（如 `lastYearsDiff` 为 "-" 字符串）

#### 12. 价格详情页收藏功能 ✅
- 心形收藏按钮：已收藏红色实心，未收藏空心轮廓
- AJAX 切换收藏状态，Toast 提示反馈（2秒）
- 收藏 ID 格式统一：`type-productSpec`
- 修复 My Favorites 页面显示问题（类型键映射：crc→crc_hard, aluminum→al）

#### 13. 实时数据字段映射 ✅
- 主价格：`lastprice_usd` / `lastpriceTax_usd`
- 涨跌值：`riseAndFall_usd` / `riseAndFallTax_usd`
- 涨跌百分比：`riseRange` / `riseRangeTax`（直接使用，已是百分比）
- 平均价：`price_usd` / `priceTax_usd`
- WoW/MoM/YoY：对应 `lastWeekDiff`、`lastMonthDiff`、`lastYearsDiff` 字段

#### 14. 午夜定时任务优化 ✅
- 00:10 任务仅保存数据，移除了缓存清除和获取新数据步骤
- 保留 17:10 缓存数据供 00:10-09:10 期间使用
- 00:10 任务直接按现有缓存落历史价格快照，并同步保存缓存中的汇率快照；不刷新最新汇率，也不重算价格列表和已缓存单规格详情的美元价格
- 01:10-08:10、18:10-23:10 的每小时汇率刷新任务已取消

#### 15. 客户端日期参数移除与服务端业务日期统一 ✅
- 首页价格表和价格详情页请求不再传 `date`
- 服务端统一决定业务日期：北京时间 9:00 之前使用昨天，9:00 及之后使用今天
- `/prices/category` 与 `/price` 端点保留 `date` 可选参数用于调试覆盖
- `class-api-client.php` 统一实现业务日期函数供价格列表与价格详情请求复用

#### 16. 价格图表 - TD（当天）数据 ✅
- `/statistics` REST API 端点：转发请求到 `www.rendui.com/api/v1/jec/rendui/prices/statistics`
- 服务端自动进行货币转换（CNY → USD），包含 `price`、`priceTax`、`rangeAvgPrice`
- 缓存策略：键为 `ippgi_stats_` + `md5(productSpec + '_' + from + '_' + to)`，过期时间 1 小时
- 客户端 TD 标签逻辑：北京时间 9:00 之前不请求数据；9:00 之后请求当天 00:00:00 ~ 当前时间的统计数据
- Canvas 绘制价格走势图：X 轴为时间戳，Y 轴为价格（USD）
- 时间范围标签（TD、1M、6M、1Y、2Y、3Y、4Y）支持点击切换，带滑动动画效果

#### 17. 支付页面实现 ✅
- 根据 Figma 设计稿重写 `/payment` 页面
- 支持 PayPal 和 Stripe 两种订阅支付方式
- 移除站点头部/底部，使用自定义页面结构
- PayPal SDK 语言自动跟随 WordPress 语言设置
- Stripe 按钮文字从 "Buy Now" 改为 "Subscribe"
- 统一按钮样式（高度 45px）

#### 18. 订阅状态与下次扣款日期 ✅
- 从 PayPal/Stripe API 获取真实的下次扣款日期
- PayPal：OAuth2 认证 → `/v1/billing/subscriptions/{id}` → `billing_info.next_billing_time`
- Stripe：`/v1/subscriptions/{id}` → `current_period_end`
- 缓存 1 小时避免频繁 API 调用
- Profile 页面显示 "Next billing date" 而非固定期限

#### 19. 邀请奖励系统重构 ✅
- 由于 PayPal/Stripe 订阅模式无法修改扣款日期，重构为累积机制
- 奖励天数单独追踪在 `ippgi_unused_bonus_days` 用户 meta
- 有活跃订阅时：奖励累积，订阅到期后自动激活
- 无订阅时：立即激活奖励天数
- 正在使用奖励时：直接延长奖励到期日期
- 奖励到期时：检查新累积天数自动续期，否则降级
- Profile 页面显示 bonus 状态和累积奖励天数

#### 20. 取消订阅功能 ✅
- Profile 页面添加 "Cancel Subscription" 按钮（带确认对话框）
- 调用 PayPal API `/v1/billing/subscriptions/{id}/cancel` 取消 PayPal 订阅
- 调用 Stripe API 设置 `cancel_at_period_end=true` 取消 Stripe 订阅
- 取消后用户可继续使用到当前计费周期结束
- 设置本地取消标记并清除缓存
- 取消后如有累积奖励天数，订阅到期时自动激活

#### 21. Stripe 订阅日期获取修复 ✅
- 修复 `ippgi_get_stripe_next_billing_date()` 函数无法获取下次扣款日期的问题
- 原因：Stripe API 返回的 `current_period_end` 在 `items.data[0]` 中，而非顶层
- 修复：同时检查顶层和 `items.data[0].current_period_end` 两个位置
- Profile 页面现在可以正确显示 Stripe 订阅的 Next billing date

#### 22. SWPM Hook 参数格式修复 ✅
- 修复 PayPal 订阅支付成功后 `admin-ajax.php` 返回 500 错误的问题
- 原因：SWPM 的 `swpm_membership_level_changed` action 传递**单个数组参数**（`array('member_id' => ..., 'from_level' => ..., 'to_level' => ...)`），但 `ippgi_on_membership_level_change()` 函数期望 3 个独立参数
- 修复：函数签名改为接收单个 `$args` 数组，从中提取 `member_id`、`from_level`、`to_level`
- `add_action` 注册从 `10, 3` 改为 `10, 1`

#### 23. 支付成功提示模态框 ✅
- 服务端检测机制：升级到 Plus 时设置 `ippgi_payment_just_completed` user meta
- 在 `wp_footer` 中检测该 meta 并清除（一次性标记）
- 根据 Figma 设计稿实现模态框 UI，替换原有 Toast 提示
- 模态框组件：半透明遮罩 + 白色渐变卡片 + 绿色打钩图标 + 标题/描述 + 蓝色按钮
- CSS 样式：`.payment-success-overlay`、`.payment-success-card` 等

**代码位置**：
- `functions.php`：`wp_footer` hook 输出模态框 HTML 和 JavaScript
- `components.css`：模态框样式（`.payment-success-*` 类）
- `membership.php`：`ippgi_on_membership_level_change()` 设置 meta 标记

#### 24. 取消订阅结束日期修复 ✅
- 修复取消订阅后 Profile 页面 "Your subscription ends on" 后面日期为空的问题
- 原因：取消后清除了缓存，PayPal API 取消后可能不再返回 `next_billing_time`
- 修复：取消前先保存结束日期到 `ippgi_subscription_end_date` user meta
- `ippgi_get_formatted_subscription_end_date()` 优先读取存储的 meta，再从 API 获取
- 重新订阅时清除 `ippgi_subscription_end_date`

#### 25. 订阅页面隐藏升级提示 ✅
- subscribe 页面不再显示升级提示 Banner（页面本身已包含订阅信息）
- `footer.php` 增加 `!is_page('subscribe')` 条件判断

#### 26. 取消订阅防重复点击 ✅
- 点击确认按钮后立即禁用，文字变为 "Cancelling..."
- 请求失败时恢复按钮状态允许重试

#### 27. SWPM Hook 重构 ✅
- 移除不可靠的 `swpm_membership_level_changed` hook
- 改用 `swpm_payment_ipn_processed` 处理首次支付和续费成功
- 使用 `swpm_subscription_payment_cancelled` 处理订阅到期
- 订阅到期时手动降级用户为 Basic（因为 SWPM Plus 等级设置为 No Expiry）
- 订阅到期时清除 `subscr_id`
- 三个 hook 都添加了 debug 日志

#### 28. PayPal 首次支付 member_id 提取修复 ✅
- 问题：PayPal 首次支付的 IPN 数据中没有 `member_id`，但 `custom` 字段包含 `swpm_id`
- 格式：`custom: "subsc_ref=125&swpm_id=1"`
- 修复：`ippgi_on_payment_success()` 函数增加从 `custom` 字段解析 `swpm_id` 的逻辑
- 使用 `parse_str()` 解析 custom 字段内容

#### 29. PayPal vs Stripe 取消订阅行为差异说明 ✅
- **PayPal**：调用取消 API 后，PayPal 立即将订阅状态改为 "CANCELLED"，并发送 IPN 通知，触发 `swpm_subscription_payment_cancelled` hook
- **Stripe**：使用 `cancel_at_period_end=true`，只是标记为"计划在周期结束时取消"，订阅仍为 `active` 状态
- Stripe 的 `swpm_subscription_payment_cancelled` hook 会在实际到期时触发（需用 test clock 推进时间测试）
- 这是两个平台的预期行为差异，不是 bug

#### 30. 取消订阅保留剩余会员期修复 ✅
- **问题**：PayPal 取消订阅时立即发送 IPN，导致用户被立即降级，丢失剩余会员期
- **修复**：
  1. 修改 `ippgi_on_subscription_expired()` 函数，根据 `subscr_id` 前缀区分 PayPal 和 Stripe
  2. **PayPal**（`I-` 前缀）：检查 `ippgi_subscription_end_date` 是否已过期，未到期则跳过降级
  3. **Stripe**（`sub_` 前缀）：直接降级，因为 Stripe 只在真正到期时才发送 webhook
  4. 添加每日定时任务 `ippgi_check_expired_cancelled_subscriptions()` 作为备份机制
- **定时任务**：每天午夜（站点时区）运行 `ippgi_check_expired_subscriptions_hook`
- **PayPal 流程**：
  1. 用户点击取消订阅 → 保存结束日期到 `ippgi_subscription_end_date`
  2. PayPal 立即发送 IPN → 检测到是 PayPal 且还没到期 → 只清除 subscr_id，保留 Plus 权限
  3. 每日定时任务检查 → 发现已过期 → 先保障降级到 Basic，再按需激活奖励访问
- **Stripe 流程**：
  1. 用户点击取消订阅 → 保存结束日期，Stripe 设置 `cancel_at_period_end=true`
  2. 计费周期真正结束时 Stripe 发送 webhook → 检测到是 Stripe → 直接降级

#### 31. PayPal API 限制调研 ✅
- **调研结论**：PayPal Subscriptions API **没有**类似 Stripe 的 `cancel_at_period_end` 功能
- PayPal 的 cancel 是**立即且最终的**，取消后无法恢复，且立即发送 IPN
- **替代方案**：PayPal 提供 `suspend`（暂停）功能，但不适合我们的场景
- **最终决策**：保持当前方案，在代码中区分 PayPal 和 Stripe 的处理逻辑
- 这是 PayPal 平台的设计限制，非代码问题

#### 32. PayPal 后台取消订阅支持 ✅
- **问题**：用户从 PayPal 后台取消订阅时，没有 `ippgi_subscription_end_date`，导致立即降级
- **原因**：网站取消会先保存结束日期再调用 API，但 PayPal 后台取消直接发 IPN
- **修复**：
  1. 收到 PayPal IPN 时，如果没有 `ippgi_subscription_end_date`，从 PayPal API 获取
  2. 优先使用 `billing_info.next_billing_time`
  3. 如果不存在（取消后可能不返回），使用 `last_payment.time` + 订阅周期计算
  4. 通过上次付款金额判断订阅周期（≥$50 为年度，否则为月度）
  5. 保存结束日期后，检查是否到期，未到期则跳过降级
- **相关函数**：`ippgi_get_paypal_next_billing_date()` 增加了备用计算逻辑

#### 33. 订阅日期时区修复 ✅
- **问题**：PayPal 返回 UTC 时间（如 `2024-02-02T00:00:00Z`），Stripe 返回 Unix 时间戳，页面展示用 UTC+8，存在 8 小时时差
- **修复**：统一使用 `wp_timezone()` 转换到站点时区后再格式化
- **修复的函数**：
  1. `ippgi_get_paypal_next_billing_date()` - PayPal UTC 字符串 → `setTimezone(wp_timezone())`
  2. `ippgi_get_stripe_next_billing_date()` - Stripe Unix 时间戳 → `DateTime('@' . $ts)` + `setTimezone(wp_timezone())`
  3. `ippgi_estimate_next_billing_date()` - SWPM 数据库日期 → `DateTime` + `setTimezone(wp_timezone())`

#### 34. 产品名称显示逻辑统一 ✅
- **背景**：部分产品类别的产品名称存在中英文混合（如 PPGI 的 "彩涂"、GI 的 "民用镀锌"），显示不统一
- **规则**：
  - **PPGI, GI, GL, CRC Hard**：只显示 `厚度*宽度`（如 `0.4*1200`）
  - **HRC, AL**：显示完整内容，包含产品名称（如 `2.0*1010 热卷`）
- **影响页面**：
  1. **价格列表页 `/prices`**：Dimensions(mm) 列
  2. **价格详情页 `/price-detail`**：产品信息表格、图表标题、曲线滑动提示框
  3. **收藏页 `/favorites`**：产品规格中间列
- **代码位置**：
  - `/assets/js/main.js`：价格列表页表格渲染逻辑（`currentType === 'hrc' || currentType === 'aluminum'` 判断）
  - `/page-templates/page-price-detail.php`：PHP 构建 `$display_dimensions`，JavaScript 构建 crosshair tooltip 的 `productName`
  - `/inc/template-functions.php`：`ippgi_get_user_favorites()` 函数中构建 `$display_spec`

#### 35. 升级提示 24 小时关闭记忆 ✅
- **需求**：升级提示关闭后，24 小时内不再显示（跨页面生效）
- **实现**：
  - 从 `sessionStorage` 改为 `localStorage` 存储关闭时间戳
  - 存储键：`ippgi_upgrade_dismissed_at`
  - 页面加载时检查：若距离上次关闭不足 24 小时则隐藏提示
  - 超过 24 小时后自动清除旧时间戳，重新显示提示
- **代码位置**：`/assets/js/main.js` 中的 `initUpgradePrompt()` 函数

#### 36. 升级提示关闭按钮重新设计 ✅
- **需求**：根据 Figma 设计稿重新设计关闭按钮
- **样式**：
  - 白色圆形背景（28px × 28px）
  - 灰色边框（1px #888）
  - X 图标使用 SVG（14px，stroke #666）
  - 位置：右上角，部分超出提示框边界（`top: -12px; right: -8px`）
  - 悬停效果：背景变为浅灰色（#f5f5f5）
- **代码位置**：
  - `/template-parts/upgrade-prompt.php`：按钮 HTML（SVG 图标）
  - `/assets/css/components.css`：`.upgrade-prompt__close` 样式

#### 37. 文章标题换行修复 ✅
- **问题**：Chrome 新版默认对 h1-h6 应用 `text-wrap: balance`，导致标题提前换行，右侧留白过多
- **修复**：对受影响的标题元素添加 `text-wrap: wrap` 覆盖默认行为
- **影响元素**：
  - `.article-card__title`（首页 Market Insights）
  - `.blog-card__title`（博客列表页）
  - `.search-result-card__title`（搜索结果页）
  - `.single-post__title`（文章详情页）
- **代码位置**：`/assets/css/components.css`

#### 38. 价格显示统一为 2 位小数 ✅
- **需求**：所有价格显示统一为 2 位小数格式（如 `$650.25`）
- **影响位置**：
  1. **首页价格列表**：Latest 列、Change 列
  2. **价格列表页 `/prices`**：Latest 列、Change 列（已有）
  3. **价格详情页 `/price-detail`**：
     - Real-Time Data 区域（6个字段：主价格、涨跌值、Avg、WoW、MoM、YoY）
     - 图表气球标签（最高点、最低点）
     - 滑动坐标点提示框价格
- **实现**：
  - 首页：`row.price.toFixed(2)` 和 `row.change.toFixed(2)`
  - 价格详情页：新增 `formatPrice(num)` 辅助函数，统一使用 `num.toFixed(2)`
- **代码位置**：
  - `/assets/js/main.js`：首页价格列表渲染
  - `/page-templates/page-price-detail.php`：价格详情页 JavaScript

#### 39. 图表气球标签宽度调整 ✅
- **问题**：价格改为 2 位小数后，气球标签内容过长导致显示不全
- **修复**：调整气球样式使其自适应内容宽度
- **样式调整**：
  - 宽度：从固定 `30px` 改为 `min-width: 36px` + `padding: 0 8px`
  - 高度：从 `36px` 改为 `24px`
  - 形状：从圆形 `border-radius: 50%` 改为药丸形 `border-radius: 12px`
  - 新增：`white-space: nowrap` 防止文字换行
- **代码位置**：`/assets/css/components.css` 中的 `.chart-balloon__text`

#### 40. Latest 列与 Change 列颜色同步 ✅
- **需求**：Latest 列颜色跟随 Change 列变化（涨跌时同步变色）
- **影响页面**：
  1. **首页价格列表**：Latest 列添加 `price-table__price--{up/down/neutral}` 类
  2. **价格列表页 `/prices`**：Latest 列添加 `prices-table__price--{up/down/neutral}` 类
- **颜色规则**：
  - 上涨（up）：绿色 `#22c55e` / `var(--color-success)`
  - 下跌（down）：红色 `#ef4444` / `var(--color-danger)`
  - 无变化（neutral）：保持默认颜色
- **代码位置**：
  - `/assets/js/main.js`：表格渲染逻辑
  - `/assets/css/components.css`：新增 `.price-table__price--up/down` 和 `.prices-table__price--up/down` 样式

#### 41. 首页产品名称动态显示 ✅
- **问题**：后台修改 Product Display Names 后，首页 MyPrices 价格列表的 Products 列不更新
- **原因**：JavaScript 使用硬编码的分类键（PPGI、GI 等），未使用后台自定义名称
- **修复**：
  1. 在 `enqueue.php` 中添加 `productNames` 映射到 `ippgiData`
  2. 更新 `main.js` 中 `buildPriceTableHTML()` 函数使用自定义名称
  3. 更新 `updateLabels()` 函数使轮播标签也使用自定义名称
- **代码位置**：
  - `/inc/enqueue.php`：`productNames` 数据传递
  - `/assets/js/main.js`：`buildPriceTableHTML()` 和 `updateLabels()` 函数

#### 42. Prices & Trends 导航权限检查 ✅
- **需求**：PC 端和移动端导航菜单的 "Prices & Trends" 链接添加权限检查逻辑
- **跳转规则**：
  - 未登录 → 登录页面 `/login/`
  - 已登录 → 价格列表页 `/prices/`
- **实现方式**：
  1. 给链接添加 `js-prices-link` 类，`href` 设为 `#`
  2. JavaScript 中复用 `navigateToPrices()` 函数处理点击事件
- **代码位置**：
  - `/template-parts/header-mobile.php`：移动端汉堡菜单
  - `/template-parts/header-desktop.php`：PC 端导航菜单
  - `/assets/js/main.js`：`initPriceTableClick()` 函数中添加 `.js-prices-link` 处理

#### 43. PC 端 LOGIN 按钮修复 ✅
- **问题**：PC 端 header 中的 LOGIN 按钮透明，只能看到边框
- **原因**：背景色设置为 `rgba(255, 255, 255, 0.15)` 在白色背景上不可见
- **修复**：根据 Figma 设计稿重新实现按钮样式
- **新样式**：
  - 背景色：`#21c9f3`（青色）
  - 边框：`1px solid #00baea`
  - 文字颜色：白色
  - 尺寸：`min-width: 89px; height: 38px`
  - 悬停效果：`background: #1ab8e0`
- **代码位置**：`/assets/css/responsive.css` 中的 `.header-login-btn`

#### 44. 登录模态框重新设计 ✅
- **问题**：登录模态框样式与 Figma 设计稿不符
- **Figma 设计**：竖向矩形（高度大于宽度，约 611px × 466px）
- **修复内容**：
  1. **模态框尺寸**：
     - 移动端：`max-width: 340px; min-height: 450px; padding: 60px 50px 50px`
     - 桌面端：`max-width: 380px; min-height: 500px; padding: 70px 55px 55px`
  2. **标题样式**：`22-24px`，颜色 `#737373`，`margin-bottom: 60-70px`
  3. **关闭按钮**：灰色圆形背景 `#b0b0b0`，定位在模态框右上角外侧
  4. **Google 登录按钮**：药丸形状，`border-radius: 28px`，2px 边框，高度 56-64px
  5. **底部条款文字**：13px，颜色 `#bbb`
- **代码位置**：
  - `/assets/css/components.css`：`.login-modal__*` 样式
  - `/assets/css/responsive.css`：桌面端覆盖样式

#### 45. Google 登录功能修复 ✅
- **问题**：点击 Google 登录按钮无响应
- **原因**：
  1. 原代码使用不存在的 `[swpm_google_login]` shortcode
  2. SWPM Social Login 插件通过 `swpm_after_login_form_output` 过滤器添加按钮
- **修复方案**：使用直接链接方式启动 Google OAuth 流程
- **实现**：
  1. 检测 SWPM Social Login 是否启用 Google 登录
  2. 如果启用，渲染直接链接 `?swpm_social_login=google`
  3. 如果未启用，回退到 SWPM 登录表单
- **Sign up 链接**：保留 "Don't have an account? Sign up" 文字，但 Sign up 使用 `<span>` 不做跳转
- **代码位置**：
  - `/template-parts/login-modal.php`：模态框模板
  - `/assets/css/components.css`：`.btn--google` 和 `.login-modal__signup-link` 样式

#### 46. PC 端支付页面左右布局 ✅
- **需求**：PC 端使用左右布局，移动端保持上下布局
- **Figma 设计**：左侧黑色背景显示价格，右侧白色背景显示表单
- **实现**：
  1. 重构 `page-payment.php` 页面结构，添加 `.payment-layout` 容器
  2. 左侧面板（`.payment-layout__left`）：渐变背景，显示订阅价格信息
  3. 右侧面板（`.payment-layout__right`）：白色背景，显示支付表单
  4. 添加响应式样式：移动端上下布局，PC 端（≥1024px）左右布局
- **样式特点**：
  - PC 端：flex 布局，左侧固定宽度，右侧自适应
  - 移动端：block 布局，上下排列
  - 左侧面板：渐变背景（深蓝到浅蓝），白色文字
  - 右侧面板：浅灰背景，居中卡片
- **代码位置**：
  - `/page-templates/page-payment.php`：页面结构
  - `/assets/css/components.css`：`.payment-layout`、`.payment-layout__left`、`.payment-layout__right` 样式

#### 47. PC 端登录逻辑统一 ✅
- **问题**：PC 端登录行为不一致
  - 点击右上角 LOGIN 按钮：弹出登录模态框
  - 点击其他需要登录的按钮（如 My favorites、Share & Earn）：跳转到单独的 `/login` 页面
- **修复**：统一为弹出模态框
- **实现方案**：
  1. 创建全局函数 `window.ippgiShowLogin()`：
     - 移动端（<1024px）：跳转到 `/login` 页面
     - PC 端（≥1024px）：弹出登录模态框
  2. 修改 `navigateToPrices()` 函数，使用 `ippgiShowLogin()` 替代直接跳转
  3. 给需要登录态的链接添加 `js-require-login` 类和 `data-href` 属性
  4. 添加 `initRequireLoginLinks()` 函数拦截这些链接的点击事件
- **受影响的链接**：
  - 桌面端导航：My favorites、Share & Earn
  - 移动端菜单：My favorites、Share & Earn
  - 首页价格表及相关跳转链接
- **代码位置**：
  - `/assets/js/main.js`：`ippgiShowLogin()`、`initRequireLoginLinks()` 函数
  - `/template-parts/header-desktop.php`：添加 `js-require-login` 类
  - `/template-parts/header-mobile.php`：添加 `js-require-login` 类

#### 48. 价格相关链接权限检查确认 ✅
- **确认内容**：PC 端和移动端，登录用户但无高级会员权限时的跳转行为
- **受影响链接**：
  - Prices & Trends（`.js-prices-link`）
  - 首页价格表容器点击
  - Read More 按钮
  - Footer 底部 6 个产品价格链接（`data-category`）
- **行为确认**：
  - 未登录 → 弹出登录模态框（PC）或跳转登录页（移动）
  - 已登录 → 正常跳转到价格页面
  - `/price-detail` 详情页同样只校验登录状态
- **代码逻辑**（`navigateToPrices()` 函数）：
  ```javascript
  if (!ippgiData.hasPremium) {
      window.location.href = ippgiData.subscribeUrl;
      return;
  }
  ```

#### 49. PC 端鼠标悬停效果 ✅
- **需求**：PC 端为可交互元素添加鼠标悬停效果，增强用户体验
- **效果设计**（参考 Figma）：
  - 浅灰背景：`#f1f1f1`
  - 1px 边框：`#a7a7a7`（使用 `inset box-shadow` 实现）
  - 3D 阴影：`4px 4px 8px rgba(0, 0, 0, 0.5)`（黑色模糊渐变）
  - 浮起动效：`transform: translateY(-2px/-3px)` + `transition 0.2s ease`
- **应用元素**：
  1. **首页价格列表行**（`.price-table tbody tr`）：浮起 2px
  2. **首页 Market Insights 文章卡片**（`.article-card`）：浮起 3px
  3. **博客列表页卡片**（`.blog-card`）：浮起 3px
- **附加修改**：博客卡片分割线从 `2px solid #e0e0e0` 改为 `1px solid var(--color-border-light)`，与首页文章卡片一致
- **代码位置**：`/assets/css/responsive.css` 中的 `@media (min-width: 1024px)` 区块
- **CSS 示例**：
  ```css
  .price-table tbody tr:hover {
      background-color: #f1f1f1;
      box-shadow:
          inset 0 0 0 1px #a7a7a7,
          4px 4px 8px rgba(0, 0, 0, 0.5);
      transform: translateY(-2px);
  }
  ```

#### 50. WP-Cron 定时任务用户修复 ✅
- **问题**：2026 年 2 月起 WordPress 后台无法上传图片
- **原因**：WP-Cron 在 root 的 crontab 中运行，2 月第一次触发时以 root 身份创建了 `uploads/2026/02` 目录（`root:root`），Web 服务器（`www-data`）无写入权限
- **修复**：
  1. 修复已有目录权限：`chown -R www-data:www-data /home/html/www/ippgi/wp-content/uploads/2026/02`
  2. 修复日志文件权限：`chown www-data:www-data /var/log/wp-cron.log`
  3. 将 WP-Cron 从 root crontab 迁移到 www-data crontab：`crontab -u www-data -e`
- **根本原因**：crontab 任务以 root 身份运行，每月第一次触发时创建新月份目录继承了 root 权限
- **预防措施**：WP-Cron 应始终以 `www-data` 用户运行，与 Web 服务器一致

#### 51. Stripe Dashboard 取消状态同步 ✅
- **问题**：用户在 Stripe Dashboard 选择 `Cancel at end of period` 后，网站在到期前无法展示 Cancelled 状态
- **修复**：
  1. 在 SWPM Stripe webhook 处理器中转发 `customer.subscription.updated` 事件（action: `swpm_stripe_subscription_updated`）
  2. 主题新增 `ippgi_on_stripe_subscription_updated()`：
     - `cancel_at_period_end=true`：写入 `ippgi_subscription_cancelled`、`ippgi_subscription_cancelled_date`、`ippgi_subscription_end_date`
     - `cancel_at_period_end=false`：清除以上取消标记（用户恢复订阅）
  3. 清理 `ippgi_next_billing_*` transient，确保 Profile 展示及时刷新
- **效果**：无论在网站还是 Stripe 后台取消，Profile 页面都能同步显示取消状态与到期时间

#### 52. 页脚社交图标统一与后台可配置 ✅
- 抽取公共模板：`/template-parts/social-icons.php`，主站页脚与 Profile 简化页脚复用同一份图标代码
- 当前展示 5 个图标（Facebook/LinkedIn/Twitter-X/Instagram/Pinterest），均为内联 SVG
- 新增 Customizer 社交链接配置项（`外观 > 自定义 > IPPGI Settings > Footer`）：
  - `ippgi_social_facebook`
  - `ippgi_social_linkedin`
  - `ippgi_social_twitter`
  - `ippgi_social_instagram`
  - `ippgi_social_pinterest`
- 未配置链接时保持 `href="#"`，配置后自动使用外链并以新标签页打开

#### 53. Gmail SMTP 邮件系统配置 ✅
- 安装并配置 WP Mail SMTP 插件。
- 使用 Gmail API (OAuth 2.0) 替代传统的 SMTP 账号密码方式，提高安全性与稳定性。
- 完成 wlg2008g@gmail.com 账号授权。
- 同步 Simple Membership (SWPM) 邮件发件人设置，确保系统邮件通过 Gmail 成功投递。
- 清理临时测试脚本及误安装的 WPForms Lite 插件。

#### 54. 高级会员欢迎邮件去重 ✅
- **现状结论**：当前 SWPM 插件在订阅 IPN 支付（PayPal/Stripe）流程中会自动发送 "Account Upgrade Notification"。
- **修正动作**：移除主题侧基于 `swpm_payment_ipn_processed` 的重复手动发信逻辑，避免用户收到两封升级通知。
- **保留逻辑**：支付成功后仍由 `ippgi_on_payment_success()` 负责显示成功模态框、清理取消状态；升级通知邮件统一交由 SWPM 插件发送。

#### 55. 订阅取消与到期邮件通知 ✅
- **分工策略**：
  - 订阅取消或过期邮件统一由 SWPM 插件内置功能自动发信（依赖后台勾选状态）。
  - 主题自定义代码不再手动补发 `Subscription Payment Canceled or Expired`，避免重复投递。
- 取消/过期邮件模板继续使用 SWPM "Subscription Payment Canceled or Expired" 配置。

#### 56. 定时任务鲁棒性优化（增量更新价格列表） ✅
- 修改 09:10-17:10 定时任务逻辑：保留 `price_list` 缓存不预先清理。
- 实现 `refresh_price_list_incrementally()` 方法，按产品分类逐个获取新价格。
- 采用“差异覆盖”策略：新获取成功的分类覆盖旧数据。
- 获取失败或为空的分类保留旧 USD 缓存原样，不再按当前最新汇率重算；但在本次刷新结束时，系统仍会对全部最终结果统一重算 `Latest($)` 区间上下限。

#### 57. 登录入口统一与重复跳转逻辑收敛 ✅
- **主入口调整**：`/login/` 作为主登录页；`/membership-login/` 仅保留兼容用途。
- **行为统一**：已登录用户访问 `/login/` 或 `/membership-login/` 时，统一在主题 `template_redirect` 阶段立即跳转首页。
- **维护优化**：移除登录页模板中的重复跳转判断，避免以后两处逻辑漏改。
- **兼容说明**：
  - SWPM `login-page-url` 应指向 `/login/`
  - Google OAuth 回调需与 `/login/?swpm-google-login=1` 保持一致
  - 首次注册成功后的 bonus、推荐关系处理仍由既有 SWPM hook + user meta 驱动，不依赖旧的 `/membership-login/` slug；注册成功欢迎弹窗当前停用

#### 58. Rendui API `phone` 请求头统一调整 ✅
- **背景**：人堆价格接口中，价格列表接口不需要自定义请求头；当前仅统计接口需要 `phone`。
- **当前规则**：
  - `prices/daily`：不传自定义请求头
  - `daily/getByProductSpecAndDate`：已停用，服务端不再请求
  - `prices/statistics`：传 `phone: 13792171909`
- **实现方式**：将需要的号码统一收敛到 `IPPGI_Prices_API_Client::API_PHONE`，并同步更新历史导入测试脚本与插件文档，避免下次改号时漏改。
- 确保在外部 API 不稳定时，前端仍能展示最后一版有效的价格列表。
- 价格转换后不再保留 `*_cny` 原始人民币字段；历史价格表也仅保留 USD 价格和汇率字段。

#### 59. Latest 区间颜色改为按区间均价比较 ✅
- **背景**：价格列表已改为显示区间值，前台不再展示 `Change` 列，因此 `Latest($)` 颜色不再适合继续依赖 `riseAndFall` / `change` 字段。
- **当前规则**：
  - 首页默认显示含税 `lastpriceTax` 区间，颜色按“本次含税区间均价”与“上一轮含税区间均价”比较决定。
  - `/prices` 页默认显示含税 `lastpriceTax` 区间，颜色按“本次含税区间均价”与“上一轮含税区间均价”比较决定。
  - 本次均价公式：`(上限 + 下限) / 2`
  - 当前均价高于上一轮：绿色 `up`
  - 当前均价低于上一轮：红色 `down`
  - 相等或缺少上一轮数据：`neutral`
- **实现细节**：
  - 仅为当前实际展示口径生成方向字段：`lastprice_range_direction_usd`、`lastpriceTax_range_direction_usd`
  - 不再为 `price` / `priceTax` 单独生成方向字段
  - 首页和 `/prices` 页前端优先读取这两个方向字段；旧的 `change` 值只作为兼容兜底

#### 60. /prices 页面 Loading 卡住修复 ✅
- **现象**：从首页 `Read More` 跳转到 `/prices` 后，价格表可能一直停留在 `Loading prices...`
- **根因**：`/prices` 页渲染逻辑调用了价格区间格式化函数，但这些函数原本只定义在首页价格轮播的局部作用域中，导致价格页初始化时报 `ReferenceError`，表格渲染中断。
- **修复动作**：
  - 将价格区间格式化函数提升到共享作用域，供首页与 `/prices` 页共用
  - `/prices` 页渲染逻辑改为调用共享格式化函数，避免再次因作用域问题中断

#### 61. WordPress 6.7+ 国际化加载时机 Notice 修复 ✅
- **现象**：`debug.log` 出现 `_load_textdomain_just_in_time was called incorrectly`，提示 `ippgi-prices` 文本域加载过早。
- **根因**：
  - `ippgi-prices` 插件之前未显式在 `init` 阶段调用 `load_plugin_textdomain()`。
  - 调度类 `cron_schedules` 过滤器中的 `__('Once Hourly', 'ippgi-prices')` 可能在 `init` 前执行，从而触发 WordPress 6.7+ 的“过早加载翻译” notice。
- **修复动作**：
  - 在插件主类中增加 `init -> load_plugin_textdomain('ippgi-prices', ...)`
  - 为 `cron_schedules` 的显示文案增加保护：`init` 前先返回纯英文字符串，避免提前触发文本域加载
  - 主题 `ippgi` 也补充了 `after_setup_theme` 阶段的 `load_theme_textdomain('ippgi', ...)`，减少后续同类风险
- **排查结论**：主题内未发现新的“文件加载阶段直接调用翻译函数”的明显同类隐患；主要风险点已修复。

#### 62. 首页中部 Banner 等比显示修正 ✅
- **问题**：首页中间 banner 之前使用固定高度（移动端 80px、平板 100px、桌面 150px）加 `object-fit: cover`，在手机、平板、PC 上都会裁剪图片内容。
- **排查结果**：
  - 模板位于 `wp-content/themes/ippgi/front-page.php`
  - 样式位于 `wp-content/themes/ippgi/assets/css/components.css` 与 `wp-content/themes/ippgi/assets/css/responsive.css`
  - 当前 Customizer 中 5 张 banner 原图尺寸约为 `485/486 x 120`，宽高比约 `4.05:1`
- **修复动作**：
  - 移除 banner 轮播容器在不同断点下的固定高度
  - 将轮播图片改为 `width: 100%`、`height: auto`，由原图比例自动撑开容器高度
  - 轮播层改为 grid 叠放，保留现有淡入淡出切换逻辑，不再依赖绝对定位 + 固定高度裁剪
- **当前行为**：banner 现为“宽度填满容器，高度按原图比例等比放大”，不会裁剪上下内容。
- **注意事项**：由于现有 banner 原图分辨率偏小，大屏下虽然比例正确，但清晰度可能一般；后续若替换素材，建议保持相同比例并提供更高分辨率版本。

#### 63. 升级通知邮件收件人改为站内会员资料邮箱 ✅
- **背景**：SWPM 插件默认会把 `Account Upgrade Notification` 发送到支付网关回调中的 `payer_email`，当 PayPal/Stripe 付款邮箱与站内会员资料邮箱不一致时，用户可能收不到站内账号对应的升级通知。
- **实现方式**：
  - 保持 SWPM 插件自动发信职责不变，不改第三方插件源码。
  - 在主题 `inc/membership.php` 中监听 `swpm_membership_level_changed`，仅对真正的升级事件记录待发送会员 ID。
  - 通过 WordPress `wp_mail` 过滤器拦截 SWPM 紧随其后的升级通知，并将收件人改写为 `SwpmMemberUtils::get_user_by_id($member_id)->email`。
- **回退策略**：若 SWPM 会员资料邮箱为空或非法，则保留原始收件人，不阻断邮件发送。

#### 64. 午夜 Cron 取消/到期邮件手动补发移除 ✅
- **背景**：项目现已统一采用 SWPM 插件自动发送 `Subscription Payment Canceled or Expired` 邮件，不再需要主题在午夜 Cron 降级时手动补发。
- **修复动作**：
  - 从 `ippgi_check_expired_cancelled_subscriptions()` 中移除手动发信调用。
  - 删除主题中的 `ippgi_send_subscription_cancelled_email()` 辅助函数，避免后续误用。
- **当前行为**：午夜 Cron 现在只负责检查到期、执行降级、激活 bonus（如有）并清理取消状态 meta；取消/过期邮件完全由 SWPM 内置逻辑负责。

#### 65. SWPM 国家下拉 PHP 8.1 Deprecated 兼容修复 ✅
- **问题**：`simple-membership/classes/class.swpm-utils-misc.php` 的 `get_countries_dropdown()` 在 PHP 8.1+ 环境下可能收到 `null` 的国家值，并将其直接传给 `strtolower()`，导致 `Passing null to parameter #1 ($string) of type string is deprecated` 日志。
- **修复动作**：
  - 在 `SwpmMiscUtils::get_countries_dropdown()` 中将传入的 `$country` 统一兜底为字符串。
  - 在国家列表遍历比较时，也将 `$country_name` 统一兜底为字符串。
  - 同时覆盖该函数中 `similar_text(strtolower(...))` 的同类潜在 warning。
- **影响范围**：仅为兼容性修复，不改变国家下拉的原有显示和匹配逻辑。
- **注意事项**：此次修复位于第三方插件 `simple-membership` 源码中，后续升级插件时需要留意该补丁是否被覆盖。

#### 66. Trial(Level 3) 残留清理与删除确认 ✅
- **结论**：当前项目代码已经不再使用 Trial (Level 3)；赠送访问统一走 bonus 机制，实际使用的 SWPM 等级仅为 Basic (2) 与 Plus (4)。
- **数据库核查结果**：
  - `swpm_membership_tbl` 中仍存在 `Trial` 等级记录。
  - `swpm_members_tbl` 中 `membership_level = 3` 的会员数为 `0`。
  - `swpm_transactions` 与 SWPM 按钮相关 meta 中未发现引用等级 `3` 的记录。
- **处理动作**：
  - 清理主题代码中最后一处 `Trial mechanism` 注释残留。
  - 文档统一更新为 bonus 机制口径，不再保留“旧 Trial 流程仍存在”的歧义。
- **后台操作建议**：基于当前数据库状态，可以直接在 WordPress 后台删除 SWPM 的 Trial(Level 3) 等级；删除前后无需额外迁移现有会员数据。

#### 67. TranslatePress 多语言系统接入 ✅
- 启用 TranslatePress + Pro Developer 插件，支持 4 种语言：
  - 默认：English (`en_US`)，根路径 `/`
  - 法语 (`fr_FR`) → `/fr/`
  - 俄语 (`ru_RU`) → `/ru/`
  - 西语 (`es_ES`) → `/es/`
- header `top-bar` 占位改为接入 TP `url_converter` 的真实语言切换器（顺序：English → Français → Русский → Español），关闭 TP 默认右下角浮动切换器
- 自动翻译引擎：Google Translate v2（避开了 TP 自带 AI 0 字符配额问题）
- 实现 `ippgiData.strings` JS 字典 + 服务端 transient 缓存，让 JS 渲染的字符串也能被 TP 翻译
- 详见下文新增章节 [`国际化 / 多语言（TranslatePress）`](#国际化--多语言translatepress)


---

### Phase 2 - 待实现

1. 价格历史图表 - 1M/6M/1Y/2Y/3Y/4Y 时间范围的历史数据图表（TD 已完成）
2. 历史数据表格 - 从数据库加载历史价格表格
3. 数据导出功能 - Plus 会员专属
4. 价格提醒功能 - 价格变动通知
5. 趋势分析 - 价格趋势分析工具
6. 邮件通知系统 - Plus 会员欢迎邮件等

---

## 部署指南

### 服务器要求
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Nginx 或 Apache
- SSL 证书（推荐）

### 部署步骤

#### 1. 准备云服务器
```bash
# 安装 LNMP/LAMP 环境
# 创建数据库和用户
```

#### 2. 导出本地数据
```bash
# 导出完整数据库
mysqldump -u root -p wordpress > ippgi_full_backup.sql

# 或只导出价格数据表
mysqldump -u root -p wordpress \
  ippgi_prices_gi \
  ippgi_prices_gl \
  ippgi_prices_ppgi \
  ippgi_prices_hrc \
  ippgi_prices_crc_hard \
  ippgi_prices_al \
  ippgi_prices_exchange_rates \
  > ippgi_prices_data.sql
```

#### 3. 上传文件
- 上传整个 WordPress 目录到服务器
- 或使用 Git 部署主题和插件

#### 4. 导入数据库
```bash
mysql -u username -p database_name < ippgi_full_backup.sql
```

#### 5. 配置 wp-config.php
- 更新数据库连接信息
- 更新站点 URL
- 关闭开发模式：`IPPGI_DEV_MODE` 设为 `false`

#### 6. 配置定时任务
```bash
# 添加到 www-data 用户的 crontab（重要：不要用 root crontab）
crontab -u www-data -e
# 添加这行：
* * * * * cd /home/html/www/ippgi && /usr/bin/php wp-cron.php >> /var/log/wp-cron.log 2>&1
```

**说明**：
- 每分钟执行一次，确保定时任务及时触发
- 使用 PHP CLI 直接执行，比 curl HTTP 请求更可靠
- 日志记录到 `/var/log/wp-cron.log`，方便调试
- **必须使用 www-data 用户运行**，否则 root 创建的上传目录会导致 Web 服务器无法写入（已踩坑，见开发进度 #50）

---

## 开发注意事项
- 价格数据展示是核心功能，需要考虑表格在移动端的展示方式
- 内容权限控制需要精细到部分内容级别（同一页面部分可见）
- **缓存策略**：缓存永不过期，由定时任务在 09:10-17:10 每小时清除并刷新
- **价格列表缓存存储**：为避免单条 transient 过大，6 个分类分别缓存，REST `/prices` 响应在服务端拼装
- 生产环境务必关闭 `IPPGI_DEV_MODE`
- **资源版本号**：开发模式下自动使用 `assets/css` + `assets/js` 中最新修改时间作为版本号
- **标题换行**：页面/区块标题统一使用 `text-wrap: wrap`，避免 Chrome `text-wrap: balance` 导致两行均分留白过大

---

## 运维工具

### 历史数据导入工具
**文件**：`/import-missing-days.php`

用于补充缺失的价格数据，支持指定日期范围。

**使用方法**：
```bash
# 查看帮助
php import-missing-days.php --help

# 导入指定日期范围
php import-missing-days.php 2026-01-24 2026-01-27

# 导入单天数据
php import-missing-days.php 2026-01-24

# 导入昨天数据（默认）
php import-missing-days.php
```

**功能**：
- 从外部 API 获取历史价格数据
- 自动获取对应日期的历史汇率
- 将数据保存到数据库（仅单点美元价，不直接补区间字段）
- 日期参数按包含首尾日期的完整自然日处理（开始 `00:00:00`、结束 `23:59:59`）；出现任一规格失败时返回非零退出码
- 上游统计接口即使返回日期范围之外的记录也会在本地按 `statistics_time` 过滤；部分失败时可加 `--only-missing`，只重试目标范围内尚无记录的产品规格
- 若某一分类因上游错误未进入当前价格缓存，优先从目标范围之前最近的历史快照读取产品规格；没有前序快照时再读取目标范围之后最近的快照，避免把目标日期本身不完整的快照当作完整规格表

生产环境的 Git 仓库与 WordPress 网站目录分离时，不要把运维脚本复制到 Web 根目录。通过环境变量加载实际站点：

```bash
IPPGI_WP_ROOT=/home/html/www/ippgi \
php /home/wlg2008g/deploy/ippgi-src/import-missing-days.php 2026-07-22
```

### 历史美元区间回填工具
**文件**：`/backfill-historical-usd-ranges.php`

用于按日期范围回填历史价格表中的 `price_usd_min` / `price_usd_max` / `price_tax_usd_min` / `price_tax_usd_max`。脚本优先从当天已有区间恢复最常见的公共因子；当天完全没有可恢复区间时才随机生成，并按“每天一组、当天全表共用”的规则补齐或规范化区间。

---

## 自定义 Logo 支持

主题支持通过 WordPress Customizer 上传自定义 logo。

**Logo 尺寸**：
- 移动端：最大高度 36px
- 桌面端：最大高度 44px
- 宽度自动按比例缩放

**CSS 选择器**：
- `.site-logo .custom-logo` - WordPress 自定义 logo 图片
- `.site-logo .custom-logo-link` - logo 链接容器
- `.site-logo__text` - 文本 logo（无图片时显示）

**注意**：WordPress 输出的 logo 图片带有内联 `width` 和 `height` 属性，CSS 中使用 `height: auto` 覆盖以确保 `max-height` 生效。

---

## Gutenberg 编辑器预览一致性（Privacy/Terms）

为解决“后台编辑预览”和“前台发布页”排版不一致问题，主题已启用编辑器样式并新增专用 CSS：

- `add_theme_support('editor-styles')`
- `add_editor_style('assets/css/editor-style.css')`

**涉及文件**：
- `wp-content/themes/ippgi/functions.php`
- `wp-content/themes/ippgi/assets/css/editor-style.css`
- `wp-content/themes/ippgi/assets/css/components.css`（`.legal-content` 前台样式）

**当前行为**：
- 后台 Gutenberg 编辑器会加载与前台法律页面接近的字体、标题层级、段落间距与列表样式
- 前台 Privacy/Terms 页面继续使用 `.legal-content` 样式渲染
- 目标是做到“编辑器预览 ≈ 前台发布效果”，尤其是标题大小层级和 `ul/ol` 列表符号

---

## MCP 配置规范
- stdio 类型：使用 command + args
- SSE 类型：必须指定 type: "sse" + url
- HTTP 类型：必须指定 type: "http" + url + headers（可选）
