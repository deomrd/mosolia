-- Regle de credit : le client peut-il rembourser avec l'epargne de son carnet ?
ALTER TABLE avecxy_credit_produits ADD COLUMN remboursement_epargne TINYINT NOT NULL DEFAULT 1;
