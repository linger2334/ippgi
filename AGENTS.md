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
- `CLAUDE.md` 用于背景说明；若与代码冲突，以代码为准，并在提交说明里标注差异。

## 4) 关键业务约束
- 会员体系（SWPM）：Basic=2，Plus=4；赠送天数走 bonus 机制（用户 meta）。
- 邮件系统：使用 WP Mail SMTP 插件通过 Gmail API 发送邮件。发件人必须与 Gmail 认证账号一致。
- 邮件通知逻辑（职责分工）：
  - **由 SWPM 插件自动发送**：
    1. 首次 Google 注册成功 (Registration Complete)。
    2. 即时触发的订阅取消/过期 (Subscription Payment Canceled or Expired)，如 Webhook 场景。
  - **由自定义代码手动补全**：
    1. 付费订阅成功后 (Account Upgrade Notification) —— 理由：SWPM 不会在 IPN 支付流程中自动触发。
    2. 每日午夜定时降级逻辑中 (Subscription Payment Canceled or Expired) —— 理由：SWPM 无法感知延迟的 Cron 逻辑。
  - **防冲突原则**：在上述“自动发送点”切勿增加手动 Hook，避免骚扰用户。
- 数据表前缀：`ippgi_`（非 `wp_`）。
- REST 命名空间：`ippgi-prices/v1`。
- 时区与调度：按 Asia/Shanghai（UTC+8）理解业务时间。
- 定时任务关键流程：
  - 00:10：先刷新最新汇率，再按最新汇率重算现有价格列表缓存和已缓存单规格详情中的美元价格（人民币价格保持不变），然后保存价格/汇率快照。
  - 01:10-08:10、18:10-23:10：每小时的 10 分刷新最新汇率，并按最新汇率重算现有价格列表缓存和已缓存单规格详情中的美元价格（人民币价格保持不变），不抓取新行情。
  - 09:10-17:10：按小时刷新价格。采用“增量更新”策略：保留现有价格列表缓存，逐个分类获取新数据；若获取失败则保留该分类的旧数据，并强制按最新汇率重新计算美元价，以确保全站汇率一致性及应对 API 不稳定。

## 5) 代码实现约定
- PHP 侧：
  - 所有输入参数做 sanitize/validate；敏感操作校验 nonce/capability。
  - 保持已有 meta key、hook 名称、接口字段兼容，避免破坏历史数据与前端解析。
- 前端（主题 JS + 模板）：
  - 若改 REST 返回结构，必须同步改调用处（首页、价格页、详情页）。
  - 涉及会员可见性的 UI 变更，需覆盖 guest/basic/bonus/plus 状态。

## 6) 变更前后检查清单
- 语法与基础检查：
  - `php -l` 检查改动过的 PHP 文件。
- 功能检查（按改动范围选择）：
  - 价格接口：`/prices/category`、`/price`、`/historical`。
  - 会员流程：登录、订阅状态展示、取消订阅、bonus 生效/到期。
  - 定时任务：不破坏现有 cron hook 与缓存策略。
- 文档同步：
  - 若你修改了业务规则/ID/时序，需同步更新 `CLAUDE.md` 与本文件。

## 7) 运维脚本使用原则
- 根目录导入脚本仅用于运维/补数，执行前确认日期范围与环境（避免误写生产）。
- 与汇率相关的导入脚本可能耗时较长，执行前明确预期与回滚方案。

## 8) 提交说明建议
- 提交信息应包含：
  - 改了什么（文件/模块）
  - 为什么改（问题或需求）
  - 风险点与验证结果
