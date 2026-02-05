# 預約系統 WordPress 外掛 by WumetaX

一個功能完整的 WordPress 預約管理系統，支援前台線上預約、後台管理、日曆檢視和時段衝突檢查。

![Version](https://img.shields.io/badge/version-2.7-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)

## 功能特色

### 🎯 核心功能
- ✅ **前台線上預約** - 響應式預約表單，支援手機和桌面
- ✅ **後台管理系統** - 完整的預約管理介面
- ✅ **日曆視圖** - FullCalendar 整合，視覺化預約狀態
- ✅ **時段衝突檢查** - 自動防止重複預約
- ✅ **Email 通知** - 自動寄送確認信給客戶和管理員
- ✅ **封鎖日期管理** - 設定休假日、國定假日等不開放預約的日期

### ⚙️ 彈性設定
- 📅 自訂營業時間（開始/結束時間）
- 🕐 可調整時段間隔（15/30/60 分鐘）
- ⏱️ 多種預約時長選項（30/60/90/120 分鐘）
- 📆 自由選擇可預約星期
- 🚫 日期區間封鎖功能

### 🎨 狀態管理
- 🟠 待確認 (Pending)
- 🟢 已確認 (Confirmed)
- 🔴 已取消 (Cancelled)
- 🔵 已完成 (Completed)

## 安裝說明

### 方法一：手動安裝

1. 下載本專案並解壓縮
2. 將 `booking-system` 資料夾上傳到 `/wp-content/plugins/` 目錄
3. 在 WordPress 後台的「外掛」頁面啟用「預約系統 by WumetaX」
4. 前往「預約管理 > 預約設定」進行初始設定

### 方法二：直接上傳

1. 登入 WordPress 後台
2. 前往「外掛 > 安裝外掛 > 上傳外掛」
3. 選擇下載的 ZIP 檔案並安裝
4. 啟用外掛

## 使用方式

### 前台顯示預約表單

在任何頁面或文章中使用以下 Shortcode：

[booking_form]


### 後台管理

1. **查看所有預約**
   - 前往「預約管理 > 所有預約」
   - 可以快速更改預約狀態
   - 支援搜尋和篩選功能

2. **日曆檢視**
   - 前往「預約管理 > 日曆檢視」
   - 視覺化查看所有預約
   - 點擊事件查看詳細資訊

3. **系統設定**
   - 前往「預約管理 > 預約設定」
   - **一般設定**：設定營業時間、時段間隔等
   - **封鎖日期管理**：新增/刪除不開放預約的日期

## 設定指南

### 營業時間設定

1. 選擇可預約的星期（週一到週日）
2. 設定營業開始時間（例如：09:00）
3. 設定營業結束時間（例如：18:00）
4. 選擇時段間隔（15/30/60 分鐘）

### 預約時長設定

1. 勾選可用的預約時長選項
2. 設定預設時長（客戶開啟表單時預選的選項）
3. 建議：勾選 30、60、90、120 分鐘提供彈性選擇

### 封鎖日期設定

#### 單一日期封鎖

開始日期：2026-02-10
結束日期：2026-02-10
備註：農曆新年

#### 日期區間封鎖

開始日期：2026-02-10
結束日期：2026-02-16
備註：春節假期


## 常見問題

### Q: 如何更改預約表單樣式？
A: 編輯 `css/booking-style.css` 檔案，或在主題的自訂 CSS 中覆寫樣式。

### Q: 可以客製化 Email 通知內容嗎？
A: 可以，編輯 `booking-system.php` 中的 `handle_booking_submission()` 函數。

### Q: 如何匯出預約資料？
A: 可使用 WordPress 內建的匯出功能，或安裝額外的匯出外掛。

### Q: 支援多語言嗎？
A: 目前介面為繁體中文，若需其他語言可使用 WPML 或 Polylang 外掛。

### Q: 資料儲存在哪裡？
A: 預約資料儲存在 WordPress 資料庫的 `wp_posts` 和 `wp_postmeta` 表中，封鎖日期儲存在 `wp_booking_blocked_dates` 表中。

## 系統需求

- **WordPress**: 5.0 或更高版本
- **PHP**: 7.4 或更高版本
- **MySQL**: 5.6 或更高版本
- **瀏覽器**: Chrome、Firefox、Safari、Edge（最新版本）

授權條款
本專案採用 GPL-2.0 授權。詳見 LICENSE 檔案。

作者資訊
開發者: WumetaX
網站: https://wumetax.com
版本: 2.7
最後更新: 2026-02-05

支援與反饋
如有問題或建議，歡迎：

📧 聯絡我們：support@wumetax.com

🐛 提交 Issue：GitHub Issues

⭐ 給予星標支持本專案

致謝
FullCalendar - 日曆功能

jQuery - JavaScript 函式庫

WordPress 社群的支持

Made with ❤️ by WumetaX
