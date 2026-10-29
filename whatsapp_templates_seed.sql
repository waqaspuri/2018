CREATE TABLE IF NOT EXISTS whatsapp_templates (
    templateLabel VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
    templateName VARCHAR(150) NOT NULL,
    mediaType VARCHAR(20) NOT NULL DEFAULT '',
    variablesJson TEXT NOT NULL,
    createdDate DATETIME NOT NULL,
    modifiedDate DATETIME NOT NULL,
    PRIMARY KEY (templateLabel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

START TRANSACTION;

INSERT INTO whatsapp_templates
    (templateLabel, templateName, mediaType, variablesJson, createdDate, modifiedDate)
VALUES
    ('Shipment Schedule', 'shipment_schedule', '', '["applicant_name","product_name","document_bill_no","departure_date","arrival_date"]', NOW(), NOW()),
    ('Shipment Schedule (Media)', 'shipment_schedule_media', 'image', '["applicant_name","product_name","document_bill_no","departure_date","arrival_date"]', NOW(), NOW()),
    ('Payment Confirmation', 'payment_confirmation', '', '["applicant_name","product_name","document_order_number","payment_terms"]', NOW(), NOW()),
    ('Shipping Information (DHL)', 'shipping_info', '', '["applicant_name","product_name","document_order_number","shipping_info"]', NOW(), NOW()),
    ('Shipment Schedule PDF BL', 'shipment_schedule_pdf', 'document', '["applicant_name","product_name","document_bill_no","departure_date","arrival_date"]', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    templateName = VALUES(templateName),
    mediaType = VALUES(mediaType),
    variablesJson = VALUES(variablesJson),
    modifiedDate = NOW();

COMMIT;
