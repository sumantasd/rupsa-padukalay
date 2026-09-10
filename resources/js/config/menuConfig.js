export const standaloneItems = [
  { name: 'Dashboard', path: '/admin/dashboard', icon: '📊', permission: null }
];

export const menuGroups = [
  {
    title: 'SALES & POS',
    icon: '🛒',
    items: [
      { name: 'New Sale / POS', path: '/admin/pos', icon: '🛒', badge: 'POS', permission: 'pos.billing' },
      { name: 'Sales Invoices', path: '/admin/sales', icon: '🧾', permission: 'sales.view|pos.billing' },
      { name: 'Sales Returns', path: '/admin/sales-returns', icon: '↩️', permission: 'sales_returns.view|pos.returns' },
      { name: 'Exchanges', path: '/admin/exchanges', icon: '🔄', permission: 'exchanges.view|pos.exchanges' },
    ]
  },
  {
    title: 'INVENTORY & STOCKS',
    icon: '📦',
    items: [
      { name: 'Stock Overview', path: '/admin/inventory/stock', icon: '📦', permission: 'inventory.view' },
      { name: 'Stock Add', path: '/admin/inventory/stock-add', icon: '➕', permission: 'inventory.adjust|inventory.view' },
      { name: 'Stock Movements', path: '/admin/inventory/movements', icon: '📈', permission: 'inventory.view' },
      { name: 'Stock Adjustments', path: '/admin/inventory/adjustments', icon: '⚙️', permission: 'inventory.adjust' },
      { name: 'Stock Transfers', path: '/admin/inventory/transfers', icon: '🚚', permission: 'inventory.transfer' },
      { name: 'Low Stock Alerts', path: '/admin/inventory/low-stock', icon: '⚠️', permission: 'inventory.view' },
      { name: 'Stock Out – Damage', path: '/admin/stock-damage', icon: '🗑️', permission: 'inventory.view' },
    ]
  },
  {
    title: 'REPORTS',
    icon: '📊',
    items: [
      { name: 'Sales Reports', path: '/admin/reports/sales', icon: '📊', permission: 'reports.view' },
      { name: 'Inventory Reports', path: '/admin/reports/inventory', icon: '📦', permission: 'reports.view' },
      { name: 'Purchase Reports', path: '/admin/reports/purchases', icon: '📄', permission: 'reports.view' },
      { name: 'Customer Reports', path: '/admin/reports/customers', icon: '👥', permission: 'reports.view' },
      { name: 'Payment Reports', path: '/admin/reports/payments', icon: '💳', permission: 'reports.view' },
      { name: 'Profit & Margin', path: '/admin/reports/profit-margin', icon: '📈', permission: 'reports.view' },
    ]
  },
  {
    title: 'MASTER DATA',
    icon: '👟',
    items: [
      { name: 'Products', path: '/admin/products', icon: '👟', permission: 'products.view' },
      { name: 'Categories', path: '/admin/categories', icon: '🏷️', permission: 'products.view', moduleKey: 'productFieldCategory' },
      { name: 'Brands', path: '/admin/brands', icon: '🏅', permission: 'products.view' },
      { name: 'Sizes', path: '/admin/sizes', icon: '📏', permission: 'products.view' },
      { name: 'Colors', path: '/admin/colors', icon: '🎨', permission: 'products.view', moduleKey: 'productFieldColor' },
      { name: 'Customers', path: '/admin/customers', icon: '👥', permission: 'customers.view' },
      { name: 'Suppliers', path: '/admin/suppliers', icon: '🏢', permission: 'suppliers.view' },
    ]
  },
  {
    title: 'PURCHASES',
    icon: '📝',
    items: [
      { name: 'Purchase Orders', path: '/admin/purchase-orders', icon: '📝', permission: 'procurement.view' },
      { name: 'Goods Receive (GRN)', path: '/admin/grn', icon: '📥', permission: 'procurement.receive' },
      { name: 'Purchase Bills', path: '/admin/purchase-bills', icon: '📑', permission: 'procurement.view' },
      { name: 'Purchase Returns', path: '/admin/purchase-returns', icon: '↩️', permission: 'procurement.view' },
    ]
  },
  {
    title: 'PAYMENTS & EXPENSES',
    icon: '💳',
    items: [
      { name: 'Payment Collections', path: '/admin/payments/collections', icon: '💳', permission: 'pos.billing' },
      { name: 'Refunds', path: '/admin/payments/refunds', icon: '💸', permission: 'pos.returns' },
      { name: 'Expenses', path: '/admin/expenses', icon: '💸', permission: 'expenses.view' },
      { name: 'Cash Drawer Status', path: '/admin/payments/cash-drawer', icon: '💵', permission: 'pos.sessions' },
      { name: 'Day Closing', path: '/admin/payments/day-closing', icon: '🔒', permission: 'pos.sessions' },
    ]
  },
  {
    title: 'STORE MANAGEMENT',
    icon: '🏬',
    items: [
      { name: 'Stores List', path: '/admin/stores', icon: '🏬', permission: 'stores.manage' },
      { name: 'Store Performance', path: '/admin/stores/performance', icon: '🎯', permission: 'reports.view' },
    ]
  },
  {
    title: 'USERS & ACCESS CONTROL',
    icon: '🛡️',
    items: [
      { name: 'Users List', path: '/admin/users', icon: '🔑', permission: 'users.manage' },
      { name: 'Roles & Permissions', path: '/admin/roles', icon: '🛡️', permission: 'roles.manage' },
      { name: 'Store Access Matrix', path: '/admin/store-access', icon: '🔒', permission: 'users.manage' },
      { name: 'System Audit Log', path: '/admin/audit', icon: '📜', permission: 'audit.view' },
    ]
  },
  {
    title: 'SETTINGS',
    icon: '⚙️',
    items: [
      { name: 'Company Profile', path: '/admin/settings/company', icon: '🏢', permission: 'system.settings|company.settings' },
      { name: 'Invoice Settings', path: '/admin/settings/invoices', icon: '🧾', permission: 'system.settings|invoice.settings' },
      { name: 'Tax Settings', path: '/admin/settings/tax', icon: '📑', permission: 'system.settings|tax.settings' },
      { name: 'Payment Methods', path: '/admin/settings/payment-methods', icon: '💳', permission: 'system.settings|payment_methods.manage' },
      { name: 'POS Settings', path: '/admin/settings/pos', icon: '⚙️', permission: 'system.settings|pos.settings' },
      { name: 'Stock Settings', path: '/admin/settings/stock', icon: '📦', permission: 'system.settings|stock.settings' },
      { name: 'Printer Settings', path: '/admin/settings/printers', icon: '🖨️', permission: 'system.settings|printer.settings' },
      { name: 'Number Series', path: '/admin/settings/number-series', icon: '🔢', permission: 'system.settings|number_series.manage' },
      { name: 'General Settings', path: '/admin/settings/general', icon: '🔧', permission: 'system.settings|general.settings' },
      { name: 'Module Settings', path: '/admin/settings/modules', icon: '🧩', permission: 'system.settings|module.settings' },
      { name: 'Database Management', path: '/admin/settings/database', icon: '🗄️', permission: 'database.manage|system.settings' },
      { name: 'Recycle Bin', path: '/admin/settings/recycle-bin', icon: '🗑️', permission: 'recycle_bin.manage|system.settings' },
    ]
  },
  {
    title: 'FRONT WEBSITE',
    icon: '🌐',
    items: [
      { name: 'Home Page', path: '/admin/front-website/home', icon: '🏠', permission: 'system.settings' },
      { name: 'Header & Footer', path: '/admin/front-website/header-footer', icon: '🎨', permission: 'system.settings' },
      { name: 'Pages', path: '/admin/front-website/pages', icon: '📄', permission: 'system.settings' },
      { name: 'Banners', path: '/admin/front-website/banners', icon: '🖼️', permission: 'system.settings' },
      { name: 'Shop Categories', path: '/admin/front-website/categories', icon: '🏷️', permission: 'system.settings' },
      { name: 'Brands / Our Partners', path: '/admin/front-website/brands', icon: '🏅', permission: 'system.settings' },
      { name: 'Contact & Business Info', path: '/admin/front-website/contact-info', icon: '📞', permission: 'system.settings' },
    ]
  },
];

