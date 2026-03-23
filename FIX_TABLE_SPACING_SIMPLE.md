# FIX TABLE COLUMN SPACING - SIMPLE PROMPT

## PROBLEM
Table columns have uneven spacing. Category and Product Name columns have too much space, others are cramped. Need uniform spacing.

## SOLUTION

**File**: `assets/styles/product.css`

**1. Replace all individual column padding with uniform padding**:

```css
/* Remove all these individual padding rules: */
.card .product-table .table th:nth-child(1) { padding: 12px 10px; }
.card .product-table .table th:nth-child(2) { padding: 12px 14px; }
.card .product-table .table th:nth-child(3) { padding: 12px 14px; }
/* etc... */

/* Add this ONE rule for ALL columns: */
.card .product-table .table th,
.card .product-table .table td {
    padding: 12px 16px !important; /* UNIFORM SPACING */
}

.card .stock-table .table th,
.card .stock-table .table td {
    padding: 12px 16px !important; /* UNIFORM SPACING */
}
```

**2. Keep only alignment overrides** (not padding):

```css
/* Only change text-align, keep same padding: */
.card .product-table .table th:nth-child(1),
.card .product-table .table td:nth-child(1) {
    text-align: center !important;
    padding: 12px 16px !important; /* Same as others */
}
```

**3. Set consistent min-widths** (remove fixed width percentages):

```css
/* Change from: */
width: 13% !important;
min-width: 140px !important;

/* To: */
width: auto !important;
min-width: 120px !important; /* Consistent min-widths */
```

---

## RESULT
✅ All columns have equal spacing (12px 16px)
✅ No more wide gaps
✅ Consistent table appearance

