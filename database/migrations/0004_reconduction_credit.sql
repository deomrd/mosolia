-- Credit : le taux et la duree sont fixes a l'octroi. A l'echeance, le reste a payer recoit
-- une penalite au taux d'interet du credit et repart pour la meme duree (reconduction).
ALTER TABLE avecxy_credit_produits ADD COLUMN reconduction TINYINT NOT NULL DEFAULT 1;
ALTER TABLE avecxy_credits ADD COLUMN duree_jours INT NULL;
ALTER TABLE avecxy_credits ADD COLUMN reconduction TINYINT NOT NULL DEFAULT 0;
