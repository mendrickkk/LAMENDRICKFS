# FIX TABLE COLUMN SPACING - PROMPT

## PROBLEM
Table columns have inconsistent spacing. Some columns (like Category, Product Name) have too much space, others are cramped. Need uniform spacing across all columns.

## GOAL
Make all table columns have equal, consistent spacing so data looks balanced and readable.

---

## SOLUTION

### File: `assets/styles/product.css`

**1. Set uniform padding for all table cells**:

Find all column-specific padding rules and replace with uniform padding:

```css
/* Remove all individual column padding rules like: */
.card .product-table .table th:nth-child(1),
.card .product-table .table td:nth-child(1) {
    padding: 12px 10px !important; /* REMOVE THIS */
}

.card .product-table .table th:nth-child(2),
.card .product-table .table td:nth-child(2) {
    padding: 12px 14px !important; /* REMOVE THIS */
}

/* Replace with uniform padding for ALL columns: */
.card .product-table .table th,
.card .product-table .table td {
    padding: 12px 16px !important; /* UNIFORM SPACING */
    text-align: left !important; /* Default alignment */
}

/* Only override alignment where needed (center/right): */
.card .product-table .table th:nth-child(1),
.card .product-table .table td:nth-child(1) {
    text-align: center !important; /* ID column centered */
    padding: 12px 16px !important; /* Same padding */
}

.card .product-table .table th:nth-child(2),
.card .product-table .table td:nth-child(2) {
    text-align: center !important; /* Image column centered */
    padding: 12px 16px !important; /* Same padding */
}
```

**2. Apply same to Stock table**:

```css
.card .stock-table .table th,
.card .stock-table .table td {
    padding: 12px 16px !important; /* UNIFORM SPACING */
    text-align: left !important;
}
```

**3. Remove inconsistent width rules**:

Keep only `min-width` for columns, remove fixed `width` percentages:

```css
/* Change from: */
width: 13% !important;
min-width: 140px !important;

/* To: */
width: auto !important;
min-width: 120px !important; /* Smaller, consistent min-widths */
```

**4. Set consistent min-widths for all columns**:

```css
/* Checkbox column */
.card .stock-table .table th:nth-child(1),
.card .stock-table .table td:nth-child(1) {
    min-width: 50px !important;
    padding: 12px 16px !important;
}

/* ID column */
.card .stock-table .table th:nth-child(2),
.card .stock-table .table td:nth-child(2) {
    min-width: 70px !important;
    padding: 12px 16px !important;
}

/* Image column */
.card .stock-table .table th:nth-child(3),
.card .stock-table .table td:nth-child(3) {
    min-width: 100px !important;
    padding: 12px 16px !important;
}

/* Product Name column */
.card .stock-table .table th:nth-child(4),
.card .stock-table .table td:nth-child(4) {
    min-width: 150px !important;
    padding: 12px 16px !important;
}

/* Category column */
.card .stock-table .table th:nth-child(5),
.card .stock-table .table td:nth-child(5) {
    min-width: 150px !important;
    padding: 12px 16px !important;
}

/* Price column */
.card .stock-table .table th:nth-child(6),
.card .stock-table .table td:nth-child(6) {
    min-width: 120px !important;
    padding: 12px 16px !important;
}

/* Quantity column */
.card .stock-table .table th:nth-child(7),
.card .stock-table .table td:nth-child(7) {
    min-width: 100px !important;
    padding: 12px 16px !important;
}

/* Created At column */
.card .stock-table .table th:nth-child(8),
.card .stock-table .table td:nth-child(8) {
    min-width: 120px !important;
    padding: 12px 16px !important;
}

/* Updated At column */
.card .stock-table .table th:nth-child(9),
.card .stock-table .table td:nth-child(9) {
    min-width: 120px !important;
    padding: 12px 16px !important;
}

/* Actions column */
.card .stock-table .table th:nth-child(10),
.card .stock-table .table td:nth-child(10) {
    min-width: 120px !important;
    padding: 12px 16px !important;
}
```

---

## KEY CHANGES

1. **Uniform Padding**: All columns use `padding: 12px 16px !important`
2. **Remove Individual Padding Rules**: Delete all `nth-child` specific padding
3. **Consistent Min-Widths**: Set reasonable min-widths (50px-150px range)
4. **Auto Width**: Use `width: auto` so columns expand equally
5. **Only Override Alignment**: Keep padding same, only change text-align where needed

---

## APPLY TO

- Product table (`templates/product/index.html.twig`)
- Stock table (`templates/stock/index.html.twig`)
- Category table (`templates/category/index.html.twig`)
- Inventory table (`templates/stock/inventory/index.html.twig`)

All use `product.css` - fix once, applies to all.

---

## RESULT

✅ All columns have equal spacing (12px 16px)
✅ No more wide gaps in Category/Product Name columns
✅ Consistent, balanced table appearance
✅ Better readability

