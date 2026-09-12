# Changelog

All notable changes to `Products for Backpack` will be documented in this file.

## 0.4 - Supplier descriptions & AI generation mode

- Feed imports (`xml:source`) can now import a product **description** from a
  supplier feed. Map it via the new **"Описание" (`fieldDescription`)** field in
  the source's upload settings (works for XML tags/paths/attributes and Excel
  columns, same as the other field mappings).
- The imported description is stored on `ak_supplier_product.description`.
- Suppliers gain a **"Режим генерации описаний ИИ" (`description_mode`)** setting
  (`scratch` | `rewrite`) exposed in the supplier admin. The package only stores
  this option and exposes `Supplier::isRewriteMode()`; the external LLM generator
  reads it to decide whether to deep-rewrite the supplier's description instead of
  generating from scratch.
- New migrations:
  - `add_description_to_supplier_product_table`
  - `add_description_mode_to_suppliers_table`
