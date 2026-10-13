ALTER TABLE pos_sale_billing
  ADD COLUMN publicGuid CHAR(36) NULL,
  ADD UNIQUE KEY uq_pos_sale_billing_publicGuid (publicGuid);
