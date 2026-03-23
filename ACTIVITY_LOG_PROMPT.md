# ACTIVITY LOG IMPLEMENTATION PROMPT

## 📋 OVERVIEW

Implement a comprehensive Activity Log system that automatically records all user actions in the Flower Shop Admin Panel. The system must use **Doctrine Entity** and **Event Subscriber** for automatic logging, and provide admin-only access to view and filter logs.

---

## 🎯 REQUIREMENTS SUMMARY

### Core Requirements:
1. **Logs record Login & Logout** ✅
2. **Logs record Create, Update, Delete actions** ✅
3. **Logs save User, Role, Action, Date/Time** ✅
4. **Logs are viewable by Admin only** ✅
5. **Must use Doctrine Entity or Event Subscriber** ✅

### Required Actions to Log:
- ✅ User login
- ✅ User logout
- ✅ Product creation
- ✅ Product update
- ✅ Product deletion
- ✅ Stock creation
- ✅ Stock update
- ✅ Stock deletion
- ✅ Category creation
- ✅ Category update
- ✅ Category deletion
- ✅ Inventory creation
- ✅ Inventory update
- ✅ Inventory deletion
- ✅ User creation (when User Management is implemented)
- ✅ User deletion (when User Management is implemented)

### Log Fields (Database):
- **userId** (integer, nullable) - ID of user who performed action
- **username** (string, 255) - Username of user who performed action
- **role** (string, 50) - User's role (ROLE_ADMIN, ROLE_STAFF, ROLE_CLIENT)
- **action** (string, 50) - Action type (LOGIN, LOGOUT, CREATE, UPDATE, DELETE)
- **targetEntity** (string, 100) - Entity type (Product, Stock, Category, Inventory, User)
- **targetId** (integer, nullable) - ID of affected record
- **targetData** (text, nullable) - Description of affected record (e.g., "Product: Rose Bouquet (ID: 14)")
- **createdAt** (datetime) - Timestamp of action

---

## 📁 FOLDER STRUCTURE

**Important**: Create the following folder structure before starting:

```
templates/admin/
  └── activity_log/  ← Create this folder first
      ├── index.html.twig
      └── show.html.twig
```

**Note**: The `activity_log` folder should be inside `templates/admin/` (same level as `templates/admin/users/`).

---

## 🏗️ IMPLEMENTATION TASKS

### 1. CREATE ACTIVITY LOG ENTITY

**File**: `src/Entity/ActivityLog.php`

**Requirements**:
- Use Doctrine ORM attributes
- Include `#[ORM\HasLifecycleCallbacks]` for automatic timestamps
- Fields:
  - `id` (primary key, auto-increment)
  - `userId` (integer, nullable, foreign key to Users)
  - `username` (string, 255, not null)
  - `role` (string, 50, not null)
  - `action` (string, 50, not null) - Values: LOGIN, LOGOUT, CREATE, UPDATE, DELETE
  - `targetEntity` (string, 100, nullable) - Entity class name (Product, Stock, Category, Inventory, User)
  - `targetId` (integer, nullable) - ID of affected record
  - `targetData` (text, nullable) - Human-readable description
  - `createdAt` (datetime, not null)
- Add `#[ORM\PrePersist]` to set `createdAt` automatically
- Add ManyToOne relationship to `Users` entity (optional, nullable)
- Add getters/setters for all fields
- Add helper method `getActionLabel()` to return human-readable action names

**Example Target Data Format**:
- Product: `"Product: Rose Bouquet (ID: 14)"`
- Stock: `"Stock: Product Rose Bouquet - Quantity: 50 (ID: 5)"`
- Category: `"Category: Bouquets (ID: 3)"`
- Inventory: `"Inventory: Product Rose Bouquet - Quantity: +10 (ID: 8)"`
- User: `"User: staff05 (ID: 9)"`

---

### 2. CREATE ACTIVITY LOG REPOSITORY

**File**: `src/Repository/ActivityLogRepository.php`

**Requirements**:
- Extend `ServiceEntityRepository<ActivityLog>`
- Add method `findRecent(int $limit = 10)` - Get recent logs ordered by createdAt DESC
- Add method `findByFilters(array $filters)` - Filter by:
  - `user` (userId or username)
  - `action` (LOGIN, LOGOUT, CREATE, UPDATE, DELETE)
  - `targetEntity` (Product, Stock, Category, Inventory, User)
  - `dateFrom` (DateTime)
  - `dateTo` (DateTime)
- Use QueryBuilder for dynamic filtering

---

### 3. CREATE ACTIVITY LOG SERVICE

**File**: `src/Service/ActivityLogService.php`

**Purpose**: Centralized service for creating activity logs programmatically.

**Requirements**:
- Inject `EntityManagerInterface` and `Security` (for getting current user)
- Add method `logAction(string $action, ?object $targetEntity = null, ?string $customTargetData = null): void`
  - Automatically get current user from Security
  - Extract user info (id, username, role)
  - Determine target entity type and ID from `$targetEntity`
  - Generate target data description
  - Create and persist ActivityLog entity
- Add helper methods:
  - `logLogin(UserInterface $user): void`
  - `logLogout(UserInterface $user): void`
  - `logCreate(object $entity): void`
  - `logUpdate(object $entity): void`
  - `logDelete(object $entity, ?string $description = null): void`
- Handle cases where user is not authenticated (e.g., login events)

**Example Usage**:
```php
// In controller
$activityLogService->logCreate($product);
// Automatically creates log with current user info
```

---

### 4. CREATE DOCTRINE EVENT SUBSCRIBER

**File**: `src/EventSubscriber/ActivityLogSubscriber.php`

**Purpose**: Automatically log all CREATE, UPDATE, DELETE operations via Doctrine events.

**Requirements**:
- Implement `EventSubscriberInterface`
- Subscribe to Doctrine events:
  - `postPersist` (CREATE)
  - `postUpdate` (UPDATE)
  - `preRemove` (DELETE - use preRemove to capture data before deletion)
- Inject `ActivityLogService` and `Security`
- Only log entities: `Product`, `Stock`, `Category`, `Inventory`, `Users`
- Skip logging if:
  - User is not authenticated (except for specific cases)
  - Entity is ActivityLog itself (prevent infinite loops)
- For DELETE: Capture entity data before removal (use `preRemove` event)
- Generate appropriate target data descriptions

**Implementation Notes**:
- Use `getSubscribedEvents()` to return array of events
- Check entity class name to determine what to log
- Use `ActivityLogService` to create logs
- Handle edge cases (null values, missing relationships)

---

### 5. CREATE SECURITY EVENT LISTENER FOR LOGIN/LOGOUT

**File**: `src/EventListener/LoginLogoutListener.php`

**Purpose**: Log user login and logout events via Symfony Security events.

**Requirements**:
- Listen to:
  - `SecurityEvents::INTERACTIVE_LOGIN` (login)
  - `LogoutEvent::class` (logout)
- Inject `ActivityLogService`
- For login: Get user from event token
- For logout: Get user from Security (before logout completes)
- Call `ActivityLogService::logLogin()` and `ActivityLogService::logLogout()`

**Implementation Notes**:
- Use `#[AsEventListener]` attribute or configure in `services.yaml`
- Handle cases where user might be null

---

### 6. CREATE ACTIVITY LOG CONTROLLER

**File**: `src/Controller/ActivityLogController.php`

**Requirements**:
- Route: `/admin/activity-logs` → `app_activity_log_index`
- Route: `/admin/activity-logs/{id}` → `app_activity_log_show` (view single log)
- Controller location: `src/Controller/ActivityLogController.php` (same level as other controllers)
- All routes must have `#[IsGranted('ROLE_ADMIN')]` attribute
- Methods:
  - `index(Request $request, ActivityLogRepository $repository)` - List all logs with filtering
  - `show(ActivityLog $activityLog)` - View single log details
- Filtering:
  - User dropdown (all users who have logs)
  - Action dropdown (LOGIN, LOGOUT, CREATE, UPDATE, DELETE)
  - Target Entity dropdown (Product, Stock, Category, Inventory, User)
  - Date range (dateFrom, dateTo)
- Pagination: Show 20 logs per page
- Order: Most recent first (createdAt DESC)

---

### 7. CREATE ACTIVITY LOG FORM TYPE (FOR FILTERING)

**File**: `src/Form/ActivityLogFilterType.php`

**Requirements**:
- Form for filtering logs
- Fields:
  - `user` (EntityType, Users, optional)
  - `action` (ChoiceType, optional) - Choices: LOGIN, LOGOUT, CREATE, UPDATE, DELETE
  - `targetEntity` (ChoiceType, optional) - Choices: Product, Stock, Category, Inventory, User
  - `dateFrom` (DateType, optional)
  - `dateTo` (DateType, optional)
- All fields should be optional
- Use `method: 'GET'` (query parameters, not POST)

---

### 8. CREATE TWIG TEMPLATES

#### 8.1 Index Template
**File**: `templates/admin/activity_log/index.html.twig`

**Note**: Make sure the `activity_log` folder exists inside `templates/admin/` before creating this file.

**Requirements**:
- Extend `admin/layout.html.twig`
- Include CSS:
  ```twig
  {% block stylesheets %}
      {{ parent() }}
      <link rel="stylesheet" href="{{ asset('styles/product.css') }}">
  {% endblock %}
  ```
- Display filter form at top
- Display table with columns:
  - Date/Time (formatted: "2025-12-06 2:18:09 PM")
  - Username
  - Role (badge: Admin/Staff/Client)
  - Action (badge: Login/Logout/Create/Update/Delete)
  - Target Entity
  - Target Data (truncated if long)
  - Actions (View Details button)
- Use pagination if implemented
- Use existing table classes from `product.css`:
  - `.table-responsive.product-table` for table wrapper
  - `.table` for table element
  - `.card`, `.card-body`, `.card-body-no-padding` for card structure
  - `.product-search-toolbar` for filter/search bar
  - `.stock-empty-state-cell`, `.stock-empty-state-message` for empty states
- Show "No logs found" message if empty (use same empty state styling as other pages)
- Add search functionality (optional, but recommended)

#### 8.2 Show Template
**File**: `templates/admin/activity_log/show.html.twig`

**Note**: This file goes in the same `templates/admin/activity_log/` folder.

**Requirements**:
- Extend `admin/layout.html.twig`
- Include CSS:
  ```twig
  {% block stylesheets %}
      {{ parent() }}
      <link rel="stylesheet" href="{{ asset('styles/product.css') }}">
  {% endblock %}
  ```
- Display log details in card/panel format:
  - Date/Time
  - User (Username, Role, User ID)
  - Action (with badge)
  - Target Entity Type
  - Target ID
  - Target Data (full description)
- Add "Back to List" button
- Make it read-only (no edit/delete buttons)

---

### 9. UPDATE SECURITY CONFIGURATION

**File**: `config/packages/security.yaml`

**Requirements**:
- Add access control rule:
  ```yaml
  - { path: ^/admin/activity-logs, roles: ROLE_ADMIN }
  ```
- Ensure staff cannot access activity logs

---

### 10. INTEGRATE INTO ADMIN SIDEBAR

**File**: `templates/admin/layout.html.twig`

**Requirements**:
- Add menu item in sidebar (admin-only):
  ```twig
  {% if is_granted('ROLE_ADMIN') %}
  <a class="menu-item {% if 'app_activity_log' in app.request.attributes.get('_route') %}menu-item-active{% endif %}" href="{{ path('app_activity_log_index') }}">
      <i class="fa fa-history menu-item-icon"></i>
      Activity Logs
  </a>
  {% endif %}
  ```
- Place after "User Management" menu item
- Use Font Awesome icon: `fa-history` or `fa-clipboard-list`

---

### 11. INTEGRATE INTO ADMIN DASHBOARD

**File**: `src/Controller/AdminController.php`

**Requirements**:
- Inject `ActivityLogRepository`
- Get recent 5-10 activity logs
- Pass to template: `'recentActivities' => $activityLogRepository->findRecent(10)`

**File**: `templates/admin/index.html.twig`

**Requirements**:
- Add "Recent Activities" section/card
- Template location: `templates/admin/activity_log/` (inside admin folder, matching `templates/admin/users/` pattern)
- Display recent logs in table/list format
- Show: Date, User, Action, Target Data (truncated)
- Add "View All Logs" link to Activity Logs page
- Limit to 5-10 most recent logs

---

### 12. INTEGRATE LOGGING INTO EXISTING CONTROLLERS

**Note**: The Event Subscriber will handle most logging automatically. However, for specific cases or custom logging, you may need to inject `ActivityLogService` into controllers.

**Controllers to Review** (Event Subscriber should handle these automatically):
- ✅ `ProductController` - CREATE, UPDATE, DELETE
- ✅ `StockController` - CREATE, UPDATE, DELETE
- ✅ `CategoryController` - CREATE, UPDATE, DELETE
- ✅ `InventoryController` - CREATE, UPDATE, DELETE
- ⚠️ `UserController` (when implemented) - CREATE, UPDATE, DELETE

**Manual Logging Required**:
- ✅ `LoginController` - LOGIN (via Security Event Listener)
- ✅ `LoginController` - LOGOUT (via Security Event Listener)

---

### 13. CREATE DATABASE MIGRATION

**Requirements**:
- Run: `php bin/console make:migration`
- Review migration file
- Run: `php bin/console doctrine:migrations:migrate`
- Ensure `activity_log` table is created with all fields

---

## 🎨 DESIGN REQUIREMENTS

### CSS Styling:
**Important**: Do NOT create a new CSS file for Activity Logs. Use existing CSS files:

1. **Include in templates**:
   ```twig
   {% block stylesheets %}
       {{ parent() }}  {# Includes admin.css from layout #}
       <link rel="stylesheet" href="{{ asset('styles/product.css') }}">
   {% endblock %}
   ```

2. **CSS Files to Use**:
   - `admin.css` - Already included via `{{ parent() }}` (layout, sidebar, header)
   - `product.css` - Use for tables, forms, cards, buttons (same as User Management, Product, Stock, Category pages)

3. **Why reuse `product.css`**:
   - ✅ Matches existing pattern (User Management uses `product.css`)
   - ✅ Consistent styling across all admin tables
   - ✅ No code duplication
   - ✅ Easier maintenance

4. **Styling Guidelines**:
   - Action badges: Use color coding (e.g., green for CREATE, blue for UPDATE, red for DELETE, gray for LOGIN/LOGOUT)
   - Role badges: Match existing role badge styles from User Management
   - Tables: Use same table classes as Product/Stock/Category pages (`.table`, `.product-table`, etc.)
   - Cards: Use same card classes (`.card`, `.card-body`, etc.)
   - Buttons: Use same button classes (`.stock-new-entry-btn`, `.card-action-btn`, etc.)

### UI/UX:
- Filter form should be collapsible/expandable (optional)
- Table should be responsive (no horizontal scrollbar)
- Date format: "2025-12-06 2:18:09 PM" (12-hour format with AM/PM)
- Pagination: Use Symfony Paginator component (optional but recommended)
- Search: Client-side JavaScript for instant filtering (optional)

---

## 🔒 SECURITY REQUIREMENTS

1. **Access Control**:
   - Only `ROLE_ADMIN` can access Activity Logs
   - Staff and Clients cannot view logs
   - Use `#[IsGranted('ROLE_ADMIN')]` on all controller methods
   - Add security.yaml rule for `/admin/activity-logs`

2. **Read-Only**:
   - Logs cannot be edited or deleted
   - No edit/delete buttons in UI
   - No routes for editing/deleting logs

3. **Data Protection**:
   - Do not log sensitive data (passwords, tokens)
   - Log only entity metadata (ID, name, type)

---

## 📝 EXAMPLE LOG RECORDS

### Example 1: User Login
```
User ID: 3
Username: admin01
Role: ROLE_ADMIN
Action: LOGIN
Target Entity: null
Target ID: null
Target Data: "User logged in"
Date & Time: 2025-12-06 10:41:25
```

### Example 2: Product Creation
```
User ID: 7
Username: staff02
Role: ROLE_STAFF
Action: CREATE
Target Entity: Product
Target ID: 14
Target Data: "Product: Laptop Asus (ID: 14)"
Date & Time: 2025-12-06 2:18:09 PM
```

### Example 3: User Deletion
```
User ID: 3
Username: admin01
Role: ROLE_ADMIN
Action: DELETE
Target Entity: User
Target ID: 9
Target Data: "User: staff05 (ID: 9)"
Date & Time: 2025-12-06 10:41:25
```

### Example 4: Stock Update
```
User ID: 7
Username: staff02
Role: ROLE_STAFF
Action: UPDATE
Target Entity: Stock
Target ID: 5
Target Data: "Stock: Product Rose Bouquet - Quantity: 50 (ID: 5)"
Date & Time: 2025-12-06 3:30:15 PM
```

---

## ✅ TESTING CHECKLIST

After implementation, verify:

- [ ] Activity Log entity created and migration run
- [ ] Login events are logged
- [ ] Logout events are logged
- [ ] Product CREATE/UPDATE/DELETE are logged
- [ ] Stock CREATE/UPDATE/DELETE are logged
- [ ] Category CREATE/UPDATE/DELETE are logged
- [ ] Inventory CREATE/UPDATE/DELETE are logged
- [ ] User CREATE/UPDATE/DELETE are logged (when User Management is implemented)
- [ ] Admin can view Activity Logs page
- [ ] Staff cannot access Activity Logs (403 error)
- [ ] Filtering works (User, Action, Date)
- [ ] Recent activities show on Admin Dashboard
- [ ] Activity Logs menu item appears in sidebar (admin only)
- [ ] Logs are read-only (no edit/delete buttons)
- [ ] Target data descriptions are accurate and readable

---

## 🔗 CONNECTION TO EXISTING SYSTEM

### Entities Connected:
- ✅ `Users` - Logged when users are created/updated/deleted
- ✅ `Product` - Logged when products are created/updated/deleted
- ✅ `Stock` - Logged when stock entries are created/updated/deleted
- ✅ `Category` - Logged when categories are created/updated/deleted
- ✅ `Inventory` - Logged when inventory movements are created/updated/deleted

### Controllers Connected:
- ✅ `ProductController` - Automatic logging via Event Subscriber
- ✅ `StockController` - Automatic logging via Event Subscriber
- ✅ `CategoryController` - Automatic logging via Event Subscriber
- ✅ `InventoryController` - Automatic logging via Event Subscriber
- ✅ `LoginController` - Login/Logout logging via Security Event Listener
- ⚠️ `UserController` - Automatic logging via Event Subscriber (when implemented)

### Templates Connected:
- ✅ `admin/layout.html.twig` - Sidebar menu item
- ✅ `admin/index.html.twig` - Recent activities section

### Security Connected:
- ✅ `security.yaml` - Access control rules
- ✅ Role-based access (ROLE_ADMIN only)

---

## 📚 TECHNICAL NOTES

### Doctrine Event Subscriber:
- Use `postPersist` for CREATE (after entity is persisted)
- Use `postUpdate` for UPDATE (after entity is updated)
- Use `preRemove` for DELETE (before entity is removed, to capture data)

### Security Events:
- `SecurityEvents::INTERACTIVE_LOGIN` - Fired when user logs in
- `LogoutEvent::class` - Fired when user logs out

### Service Configuration:
- Register `ActivityLogService` as a service in `services.yaml` (auto-configured)
- Register `ActivityLogSubscriber` as a service (auto-configured)
- Register `LoginLogoutListener` as an event listener (use `#[AsEventListener]` or configure in `services.yaml`)

---

## 🎯 FINAL NOTES

1. **Automatic Logging**: The Event Subscriber will automatically log all CRUD operations. You don't need to manually add logging code to every controller method.

2. **Login/Logout**: These are handled separately via Security Event Listener because they don't involve Doctrine entities.

3. **Target Data Format**: Keep descriptions concise but informative. Include entity type, name/identifier, and ID.

4. **Performance**: Consider adding database indexes on `userId`, `action`, `targetEntity`, and `createdAt` for faster filtering.

5. **Future Enhancements**: Consider adding export functionality (CSV/PDF) for logs, but this is not required for initial implementation.

---

**END OF PROMPT**

