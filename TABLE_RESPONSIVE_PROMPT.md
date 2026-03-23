# TABLE RESPONSIVE FIX - PROMPT

## OBJECTIVE
Remove horizontal scrollbars from all admin data tables. Make all table columns visible without scrolling. Apply to: **Product**, **Stock**, **Category**, and **Inventory** pages (all use `product.css`).

---

## CURRENT PROBLEM
- Tables have `overflow-x: auto` causing horizontal scrollbar
- Fixed column widths don't fit viewport
- Users must scroll horizontally to see all data

---

## REQUIREMENTS

### 1. Remove Horizontal Scrollbar
**File**: `assets/styles/product.css`

**Find and Remove**:
- Line ~616: `.card .table-responsive.product-table { overflow-x: auto; }`
- Line ~1128: `.card .table-responsive.stock-table { overflow-x: auto !important; }`
- Line ~1033: `.card-body-no-padding .table-responsive.product-table { overflow-x: auto; }`

**Replace with**:
```css
.card .table-responsive.product-table {
    width: 100%;
    overflow-x: visible; /* Changed from auto */
    box-sizing: border-box;
}

.card .table-responsive.stock-table {
    overflow-x: visible !important; /* Changed from auto */
    overflow-y: visible !important;
}

.card-body-no-padding .table-responsive.product-table {
    overflow-x: visible; /* Changed from auto */
}
```

### 2. Make Tables Responsive
**Change table layout from fixed to auto**:

**Find**:
```css
.card .product-table .table {
    width: 100% !important;
    table-layout: fixed !important; /* This causes overflow */
    ...
}
```

**Replace with**:
```css
.card .product-table .table {
    width: 100% !important;
    table-layout: auto !important; /* Changed from fixed */
    border-collapse: collapse !important;
    margin: 0 !important;
    box-sizing: border-box;
}
```

**Also update stock table**:
```css
.card .stock-table .table {
    width: 100% !important;
    table-layout: auto !important; /* Changed from fixed */
    ...
}
```

### 3. Adjust Column Widths
**Make column widths flexible instead of fixed percentages**:

**Current**: Columns use fixed `width: X%` and `min-width: Xpx`

**Change to**: Use `min-width` only, let columns auto-size based on content

**Example for Product Table**:
```css
/* ID Column - Smaller, flexible */
.card .product-table .table th:nth-child(1),
.card .product-table .table td:nth-child(1) {
    width: auto !important; /* Changed from fixed 70px */
    min-width: 60px !important; /* Reduced from 70px */
    padding: 12px 8px !important; /* Reduced padding */
    text-align: center !important;
}

/* Image Column - Compact */
.card .product-table .table th:nth-child(2),
.card .product-table .table td:nth-child(2) {
    width: auto !important; /* Changed from fixed 90px */
    min-width: 80px !important; /* Reduced from 90px */
    padding: 12px 8px !important; /* Reduced padding */
}

/* Name Column - Flexible */
.card .product-table .table th:nth-child(3),
.card .product-table .table td:nth-child(3) {
    width: auto !important; /* Changed from fixed 13% */
    min-width: 120px !important; /* Reduced from 140px */
    padding: 12px 10px !important; /* Reduced padding */
}

/* Continue for all columns - use auto width, smaller min-widths, reduced padding */
```

### 4. Optimize Column Sizes
**Reduce padding and min-widths to fit more content**:

- **ID**: 60px min-width (was 70px)
- **Image**: 80px min-width (was 90px)
- **Name**: 120px min-width (was 140px)
- **Category**: 100px min-width (was 130px)
- **Price**: 100px min-width (was 120px)
- **Quantity**: 80px min-width (was 110px)
- **Description**: 150px min-width (was 200px) - use text truncation if needed
- **Actions**: 100px min-width (was 110px)

### 5. Text Truncation for Long Content
**Add ellipsis for long text in description/name columns**:

```css
.card .product-table .table td:nth-child(3) strong,
.card .product-table .table td:nth-child(7) {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
}
```

### 6. Responsive Breakpoints (Optional)
**Add media queries for smaller screens**:

```css
@media (max-width: 1400px) {
    .card .product-table .table th,
    .card .product-table .table td {
        padding: 10px 8px !important; /* Further reduce padding */
        font-size: 0.8rem !important; /* Slightly smaller font */
    }
}

@media (max-width: 1200px) {
    .card .product-table .table th:nth-child(7), /* Description */
    .card .product-table .table td:nth-child(7) {
        display: none; /* Hide description on small screens */
    }
}
```

---

## APPLY TO ALL TABLES

### Product Table
- File: `templates/product/index.html.twig`
- CSS: `product.css` - `.product-table`

### Stock Table
- File: `templates/stock/index.html.twig`
- CSS: `product.css` - `.stock-table`

### Category Table
- File: `templates/category/index.html.twig`
- CSS: `product.css` - `.product-table`

### Inventory Table
- File: `templates/stock/inventory/index.html.twig`
- CSS: `product.css` - `.product-table`

---

## TESTING CHECKLIST

After changes, verify:
- [ ] No horizontal scrollbar appears
- [ ] All columns are visible without scrolling
- [ ] Table fits within viewport width
- [ ] Text doesn't overflow cells
- [ ] Columns resize based on content
- [ ] Works on Product page
- [ ] Works on Stock page
- [ ] Works on Category page
- [ ] Works on Inventory page
- [ ] Table still looks clean and readable

---

## KEY CHANGES SUMMARY

1. **Remove**: `overflow-x: auto` → Change to `overflow-x: visible`
2. **Change**: `table-layout: fixed` → Change to `table-layout: auto`
3. **Update**: Column widths from fixed `width: X%` → `width: auto` with smaller `min-width`
4. **Reduce**: Padding and min-widths to fit more columns
5. **Add**: Text truncation for long content

---

**END OF PROMPT**

