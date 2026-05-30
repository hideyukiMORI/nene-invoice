USE nene_invoice;
SET @now = '2026-05-31 00:00:00';
INSERT INTO organizations (id,name,slug,plan,is_active,created_at,updated_at) VALUES
 (1,'Org A','org-a','free',1,@now,@now),
 (2,'Org B','org-b','free',1,@now,@now);
INSERT INTO users (id,email,password_hash,role,organization_id,status,created_at,updated_at) VALUES
 (1,'admin-a@a.test','$2y$12$kJCvSpCNb0BlWYERWSoRBuqGOjY3jYaEJyjtz.CiwyKyrTIoyRWlu','admin',1,'active',@now,@now),
 (2,'member-a@a.test','$2y$12$kJCvSpCNb0BlWYERWSoRBuqGOjY3jYaEJyjtz.CiwyKyrTIoyRWlu','member',1,'active',@now,@now),
 (3,'admin-b@b.test','$2y$12$kJCvSpCNb0BlWYERWSoRBuqGOjY3jYaEJyjtz.CiwyKyrTIoyRWlu','admin',2,'active',@now,@now),
 (4,'root@root.test','$2y$12$kJCvSpCNb0BlWYERWSoRBuqGOjY3jYaEJyjtz.CiwyKyrTIoyRWlu','superadmin',NULL,'active',@now,@now),
 (5,'viewer-a@a.test','$2y$12$kJCvSpCNb0BlWYERWSoRBuqGOjY3jYaEJyjtz.CiwyKyrTIoyRWlu','viewer',1,'active',@now,@now);
INSERT INTO clients (id,organization_id,name,contact_name,email,created_at,updated_at) VALUES
 (1,1,'Client A1','Taro','c1@a.test',@now,@now),
 (2,2,'Client B1 SECRET','Hanako','c1@b.test',@now,@now);
INSERT INTO company_settings (id,organization_id,legal_name,registration_number,created_at,updated_at) VALUES
 (1,1,'Org A KK','T1234567890123',@now,@now),
 (2,2,'Org B KK','T9999999999999',@now,@now);
INSERT INTO invoices (id,organization_id,client_id,status,invoice_number,is_qualified_invoice,subtotal_cents,tax_cents,total_cents,issued_at,created_at,updated_at) VALUES
 (1,1,1,'issued','INV-2026-001',0,100000,10000,110000,@now,@now,@now),
 (2,2,2,'issued','INV-2026-001',0,200000,20000,220000,@now,@now,@now);
