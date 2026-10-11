-- Rattache chaque benefice au carnet ou au credit qui l'a produit (recalcul et corbeille),
-- relie une demande de credit au credit accorde, et une demande d'abonnement au forfait voulu.
ALTER TABLE avecxy_benefices ADD COLUMN carnet_id INT NULL;
ALTER TABLE avecxy_benefices ADD COLUMN credit_id INT NULL;
CREATE INDEX idx_ben_carnet ON avecxy_benefices (carnet_id);
CREATE INDEX idx_ben_credit ON avecxy_benefices (credit_id);
ALTER TABLE avecxy_credit_eligibilites ADD COLUMN credit_id INT NULL;
ALTER TABLE avecxy_abonnement_paiements ADD COLUMN plan_id INT NULL;
