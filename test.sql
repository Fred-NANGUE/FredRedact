-- FredRedact: rapport de test pour le stage AGB de l'utilisateur id=1.
-- Importez ce fichier dans phpMyAdmin en ayant sélectionné la base fredredact.
-- Le script peut être réexécuté sans dupliquer le rapport ni ses sections.

START TRANSACTION;

SET @test_user_id := 1;
SET @test_internship_id := (
    SELECT id
    FROM internships
    WHERE user_id = @test_user_id
      AND entreprise = 'AFRICA GOLDEN BANK'
      AND date_debut = '2026-06-03'
      AND date_fin = '2026-08-03'
    ORDER BY id DESC
    LIMIT 1
);
SET @test_report_title := 'TEST - Rapport de stage niveau 2 - AFRICA GOLDEN BANK';
SET @test_report_id := NULL;

INSERT INTO rapports (user_id, internship_id, titre, statut, progression, created_at, updated_at)
SELECT @test_user_id, @test_internship_id, @test_report_title, 'brouillon', 0, NOW(), NOW()
WHERE @test_internship_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM rapports
      WHERE user_id = @test_user_id
        AND internship_id = @test_internship_id
        AND titre = @test_report_title
  );

SET @test_report_id := (
    SELECT id
    FROM rapports
    WHERE user_id = @test_user_id
      AND internship_id = @test_internship_id
      AND titre = @test_report_title
    ORDER BY id DESC
    LIMIT 1
);

INSERT INTO report_sections (rapport_id, parent_id, titre, type, contenu, ordre, created_at, updated_at)
SELECT @test_report_id, NULL, section_data.titre, section_data.type, section_data.contenu, section_data.ordre, NOW(), NOW()
FROM (
    SELECT
        'Introduction générale' AS titre,
        'introduction' AS type,
        'Ce rapport porte sur le thème « CONCEPTION ET MISE EN PLACE D’UNE SOLUTION DE MONITORING D’UNE BASE DE DONNÉES EN ENVIRONNEMENT BANCAIRE : CAS PRATIQUE D’AFRICA GOLDEN BANK (AGB) ». Le stage s’est déroulé du 3 juin au 3 août 2026 au poste de stagiaire DBA, sous l’encadrement de KUATE KAMGUE Godwin. L’objectif de ce document est de présenter le contexte du stage, la problématique étudiée, la démarche suivie et les éléments de solution retenus. Les activités réellement effectuées, les outils utilisés et les résultats obtenus doivent être complétés et validés par le stagiaire.' AS contenu,
        1 AS ordre
    UNION ALL
    SELECT
        'Chapitre I - Présentation du cadre de stage',
        'chapitre',
        'Ce chapitre présente l’organisme d’accueil, son organisation et le service dans lequel le stage a été réalisé. Les informations institutionnelles, les missions du service et les responsabilités confiées au stagiaire sont à renseigner à partir des documents de l’entreprise et des observations effectuées pendant le stage.',
        2
    UNION ALL
    SELECT
        'Chapitre II - Contexte et problématique',
        'chapitre',
        'Le sujet concerne la conception et la mise en place d’une solution de monitoring d’une base de données en environnement bancaire. La problématique, ses enjeux, le périmètre technique et les contraintes de sécurité doivent être précisés à partir des besoins exprimés par l’entreprise. Aucune architecture ni difficulté opérationnelle n’est présumée dans ce contenu de test.',
        3
    UNION ALL
    SELECT
        'Chapitre III - Analyse des besoins et démarche',
        'chapitre',
        'Cette partie est destinée à formaliser les besoins fonctionnels et non fonctionnels, les indicateurs à surveiller, les utilisateurs concernés ainsi que la méthode de travail adoptée. Les choix présentés devront correspondre aux échanges avec l’encadrant et aux constats réellement réalisés.',
        4
    UNION ALL
    SELECT
        'Chapitre IV - Conception et réalisation',
        'chapitre',
        'Décrire ici l’architecture effectivement conçue, les outils et technologies réellement utilisés, les étapes de configuration et les mécanismes de collecte et de visualisation mis en œuvre. Ajouter des schémas ou captures d’écran pertinents après vérification qu’ils ne contiennent aucune donnée confidentielle.',
        5
    UNION ALL
    SELECT
        'Chapitre V - Tests et résultats',
        'chapitre',
        'Présenter les scénarios de test exécutés, les critères d’acceptation, les observations et les résultats mesurés. Insérer uniquement des résultats vérifiables issus du travail de stage; les valeurs, incidents et performances ne sont pas inventés dans ce rapport de démonstration.',
        6
    UNION ALL
    SELECT
        'Conclusion générale et perspectives',
        'conclusion',
        'La conclusion résumera les travaux réellement accomplis, répondra à la problématique à partir des résultats obtenus et présentera les limites ainsi que les perspectives. Elle reste à finaliser après validation des chapitres précédents par le stagiaire et son encadrant.',
        7
) AS section_data
WHERE @test_report_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM report_sections existing_section
      WHERE existing_section.rapport_id = @test_report_id
        AND existing_section.titre = section_data.titre
  );

COMMIT;

SELECT
    CASE
        WHEN @test_internship_id IS NULL THEN 'ERREUR: stage AGB de l’utilisateur id=1 non trouvé; aucune donnée insérée.'
        WHEN @test_report_id IS NULL THEN 'ERREUR: le rapport de test n’a pas pu être créé.'
        ELSE 'OK: rapport de test prêt dans FredRedact.'
    END AS resultat,
    @test_report_id AS rapport_id,
    (SELECT COUNT(*) FROM report_sections WHERE rapport_id = @test_report_id) AS nombre_sections;
