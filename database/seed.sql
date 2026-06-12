-- =============================================================================
-- PROJECT  : Student Requests & Complaints Ticket Tracking System
-- FILE     : seed.sql

USE `ticket_system`;

-- =============================================================================
-- SECTION 1: DEFAULT ADMIN ACCOUNT
--
-- Username : admin
-- Password : Admin@2025
--
-- The hash below is generated with:
--   password_hash('Admin@2025', PASSWORD_BCRYPT)
--
-
INSERT INTO `users`
    (`role`, `username`, `first_name`, `last_name`, `password_hash`,
     `group_name`, `filiere`, `account_status`)
VALUES
    ('admin', 'admin', 'System', 'Administrator',
     '$2y$10$POuGSKtVZQ3qxpsUAMzXzecnYUeRC8O2bV5YXLMAbBrSK/RTqhTYm',
     NULL, NULL, 'active');

-- Hash above = password_hash('Admin@2025', PASSWORD_BCRYPT)

-- =============================================================================
-- SECTION 2: SAMPLE STUDENT ACCOUNTS

INSERT INTO `users`
    (`role`, `username`, `first_name`, `last_name`, `password_hash`,
     `group_name`, `filiere`, `account_status`)
VALUES
    -- Student 1: DWB102 → Web Development
    ('student', 'asaassoukaina', 'SOUKAINA', 'ASAAS',
     '$2y$10$azetgaTUEn4h1d5VVf2DN.aFmtCpEwbjDnxVoV0V.vgVdRgU4RJoW',
     'DWB102', 'Web Development', 'inactive'),

    -- Student 2: DWB102 → Web Development
    ('student', 'chebahanane', 'HANANE', 'CHEBA',
     '$2y$10$azetgaTUEn4h1d5VVf2DN.aFmtCpEwbjDnxVoV0V.vgVdRgU4RJoW',
     'DWB102', 'Web Development', 'inactive'),

    -- Student 3: DMB201 → Mobile Development
    ('student', 'elidrissimohamedamine', 'MOHAMED AMINE', 'EL IDRISSI',
     '$2y$10$azetgaTUEn4h1d5VVf2DN.aFmtCpEwbjDnxVoV0V.vgVdRgU4RJoW',
     'DMB201', 'Mobile Development', 'inactive'),

    -- Student 4: DMB201 → Mobile Development
    ('student', 'benaliyoussef', 'YOUSSEF', 'BENALI',
     '$2y$10$azetgaTUEn4h1d5VVf2DN.aFmtCpEwbjDnxVoV0V.vgVdRgU4RJoW',
     'DMB201', 'Mobile Development', 'inactive'),

    -- Student 5: DWB103 → Web Development
    ('student', 'elmansourifatima', 'FATIMA', 'EL MANSOURI',
     '$2y$10$azetgaTUEn4h1d5VVf2DN.aFmtCpEwbjDnxVoV0V.vgVdRgU4RJoW',
     'DWB103', 'Web Development', 'inactive');

-- =============================================================================
-- SECTION 3: CATEGORIES
--

--   'request'   → student is asking for something from administration
--   'complaint' → student is reporting a problem
--
-- 4 request categories + 3 complaint categories = 7 total
--
-- IDs assigned in insertion order:
--   1 = Demandes administratives   (request)
--   2 = Demandes techniques        (request)
--   3 = Activités & événements     (request)
--   4 = Suggestions                (request)
--   5 = Réclamations pédagogiques  (complaint)
--   6 = Réclamations techniques    (complaint)
--   7 = Réclamations administratives (complaint)

INSERT INTO `categories` (`type`, `name`, `description`, `is_active`) VALUES

    -- -------------------------------------------------------------------------
    -- REQUEST CATEGORIES
    -- These are things students ASK FOR from the administration.
    -- -------------------------------------------------------------------------

    -- Category 1: Demandes administratives
    -- Student needs an official document issued by the administration
    -- (attestation, certificate, convention de stage, etc.)
    ('request', 'Demandes administratives',
        'Toute demande de document officiel : attestations, certificats, conventions de stage.',
        1),

    -- Category 2: Demandes techniques
    -- Student reports or asks for help with a technical tool
    -- (LMS platform, dev environment, computer issues)
    ('request', 'Demandes techniques',
        'Demandes liées aux outils techniques : plateforme LMS, environnements de développement, équipements.',
        1),

    -- Category 3: Activités & événements
    -- Student wants to organize an event, create a club, or reserve a room
    -- (hackathon, workshop, club proposal, room booking)
    ('request', 'Activités & événements',
        'Propositions d''événements, demandes d''organisation de clubs, réservations de salles et matériels.',
        1),

    -- Category 4: Suggestions
    -- Student proposes improvements (new course, platform enhancement)
    -- These are ideas, not problems — positive input from students
    ('request', 'Suggestions',
        'Suggestions d''amélioration : nouveaux cours, améliorations de la plateforme ou des services.',
        1),

    -- -------------------------------------------------------------------------
    -- COMPLAINT CATEGORIES
    -- These are problems students REPORT to the administration.
    -- -------------------------------------------------------------------------

    -- Category 5: Réclamations pédagogiques
    -- Problems related to teaching, trainers, or classmates
    -- (trainer behavior, colleague conflict, academic issues)
    ('complaint', 'Réclamations pédagogiques',
        'Réclamations liées à la pédagogie : problèmes avec un formateur ou des collègues.',
        1),

    -- Category 6: Réclamations techniques
    -- Technical problems that block the student''s work
    -- (platform down, no internet, broken computer, no access to resources)
    ('complaint', 'Réclamations techniques',
        'Réclamations liées aux problèmes techniques : plateforme, connexion, ordinateurs, matériel.',
        1),

    -- Category 7: Réclamations administratives
    -- Administrative errors or delays affecting the student''s file
    -- (late attestation, document error, stagiaire file problem)
    ('complaint', 'Réclamations administratives',
        'Réclamations liées à l''administration : retards, erreurs dans les documents, problèmes de dossier.',
        1);

-- SECTION 4: SUBCATEGORIES
--
-- Each subcategory is linked to its parent category by category_id.
--
-- Subcategory IDs assigned in insertion order:
--
--   Under cat 1 — Demandes administratives:
--     1  = Attestation de poursuite de formation
--     2  = Attestation de réussite
--     3  = Convention de stage
--     4  = Demande de certificat de formation
--
--   Under cat 2 — Demandes techniques:
--     5  = Problème avec plateforme (LMS)
--     6  = Bug dans environnement de dev / pc
--
--   Under cat 3 — Activités & événements:
--     7  = Proposition d'événement tech (hackathon, workshop)
--     8  = Organisation d'un club (dev, AI, design...)
--     9  = Demande d'autorisation événement
--     10 = Réservation salle / matériel
--
--   Under cat 4 — Suggestions:
--     11 = Suggestion d'ajout de cours (AI, cybersécurité...)
--     12 = Proposition d'amélioration plateforme
--
--   Under cat 5 — Réclamations pédagogiques:
--     13 = Problème avec formateur
--     14 = Problème avec les collègues
--
--   Under cat 6 — Réclamations techniques:
--     15 = Plateforme ne fonctionne pas
--     16 = Problème de connexion
--     17 = Problème ordinateur
--     18 = Problème accès ressources
--     19 = Problème matériel informatique
--
--   Under cat 7 — Réclamations administratives:
--     20 = Retard attestation
--     21 = Erreur dans un document
--     22 = Problème dossier stagiaire
-- =============================================================================

INSERT INTO `subcategories` (`category_id`, `name`, `description`, `is_active`) VALUES

    -- =========================================================================
    -- Under: Demandes administratives (category_id = 1)
    -- Documents that administration must prepare and sign for the student
    -- =========================================================================
    (1, 'Attestation de poursuite de formation',
        'Document officiel confirmant que l''étudiant est actuellement inscrit et en cours de formation.', 1),
    (1, 'Attestation de réussite',
        'Document officiel attestant que l''étudiant a validé sa formation avec succès.', 1),
    (1, 'Convention de stage',
        'Document tripartite entre l''étudiant, l''établissement et l''entreprise d''accueil pour un stage.', 1),
    (1, 'Demande de certificat de formation',
        'Certificat officiel mentionnant la filière, la durée et le niveau de la formation suivie.', 1),

    -- =========================================================================
    -- Under: Demandes techniques (category_id = 2)
    -- Technical requests — student needs help with a tool or system
    -- =========================================================================
    (2, 'Problème avec plateforme (LMS)',
        'Difficulté d''accès ou dysfonctionnement sur la plateforme d''apprentissage en ligne (LMS).', 1),
    (2, 'Bug dans environnement de dev / pc',
        'Problème technique avec un environnement de développement ou un poste informatique fourni par l''établissement.', 1),

    -- =========================================================================
    -- Under: Activités & événements (category_id = 3)
    -- Student wants to organize or participate in an event or club
    -- =========================================================================
    (3, 'Proposition d''événement tech (hackathon, workshop)',
        'Proposition d''organiser un événement technique ouvert aux étudiants : hackathon, atelier, conférence.', 1),
    (3, 'Organisation d''un club (dev, AI, design…)',
        'Demande de création ou de reconnaissance officielle d''un club étudiant thématique.', 1),
    (3, 'Demande d''autorisation événement',
        'Demande d''autorisation administrative pour organiser un événement au sein de l''établissement.', 1),
    (3, 'Réservation salle / matériel',
        'Demande de réservation d''une salle ou de matériel pour un usage pédagogique ou associatif.', 1),

    -- =========================================================================
    -- Under: Suggestions (category_id = 4)
    -- Positive input — student proposes improvements or new content
    -- =========================================================================
    (4, 'Suggestion d''ajout de cours (AI, cybersécurité…)',
        'Proposition d''intégrer un nouveau module ou cours dans le programme de formation.', 1),
    (4, 'Proposition d''amélioration plateforme',
        'Suggestion d''amélioration fonctionnelle ou visuelle de la plateforme numérique de l''établissement.', 1),

    -- =========================================================================
    -- Under: Réclamations pédagogiques (category_id = 5)
    -- Problems related to the teaching environment
    -- =========================================================================
    (5, 'Problème avec formateur',
        'Réclamation concernant le comportement, la méthode ou le contenu d''enseignement d''un formateur.', 1),
    (5, 'Problème avec les collègues',
        'Réclamation concernant un conflit ou un comportement inapproprié de la part d''un autre étudiant.', 1),

    -- =========================================================================
    -- Under: Réclamations techniques (category_id = 6)
    -- Technical issues blocking the student from working
    -- =========================================================================
    (6, 'Plateforme ne fonctionne pas',
        'La plateforme LMS est inaccessible, lente ou présente des erreurs bloquantes.', 1),
    (6, 'Problème de connexion',
        'L''étudiant ne peut pas se connecter à internet ou au réseau de l''établissement.', 1),
    (6, 'Problème ordinateur',
        'Panne ou dysfonctionnement d''un ordinateur mis à disposition par l''établissement.', 1),
    (6, 'Problème accès ressources',
        'L''étudiant ne peut pas accéder à des ressources pédagogiques en ligne (cours, vidéos, fichiers).', 1),
    (6, 'Problème matériel informatique',
        'Dysfonctionnement d''un équipement informatique : clavier, écran, souris, imprimante, etc.', 1),

    -- =========================================================================
    -- Under: Réclamations administratives (category_id = 7)
    -- Administrative delays or errors affecting student documents/records
    -- =========================================================================
    (7, 'Retard attestation',
        'L''attestation demandée n''a pas été délivrée dans les délais attendus.', 1),
    (7, 'Erreur dans un document',
        'Un document officiel contient une erreur (nom, date, filière, etc.) nécessitant une correction.', 1),
    (7, 'Problème dossier stagiaire',
        'Problème administratif lié au dossier de stage : convention manquante, validation en attente, etc.', 1);

-- =============================================================================
-- SECTION 5: SAMPLE TICKETS

INSERT INTO `tickets` (
    `reference`, `user_id`, `assigned_to`,
    `category_id`, `subcategory_id`, `type`, `priority`,
    `subject`, `description`, `status`,
    `rejection_reason`, `submitted_at`, `resolved_at`, `created_at`
) VALUES

    -- Ticket 1: NEW — waiting for admin to open
    -- Category: Demandes administratives (1) → Attestation de réussite (2)
    (
        'TKT-2025-00001',
        2,      -- asaassoukaina
        NULL,   -- not yet assigned
        1,      -- Demandes administratives
        2,      -- Attestation de réussite
        'request', 'medium',
        'Demande d''attestation de réussite pour dossier de stage',
        'Bonjour, j''ai besoin d''une attestation de réussite officielle afin de constituer mon dossier de candidature pour un stage en entreprise. Merci de traiter ma demande dans les meilleurs délais.',
        'new',
        NULL,
        NOW() - INTERVAL 2 DAY,
        NULL,
        NOW() - INTERVAL 2 DAY
    ),

    -- Ticket 2: IN_PROGRESS — assigned to admin
    -- Category: Réclamations pédagogiques (5) → Problème avec formateur (13)
    (
        'TKT-2025-00002',
        3,      -- chebahanane
        1,      -- assigned to admin
        5,      -- Réclamations pédagogiques
        13,     -- Problème avec formateur
        'complaint', 'high',
        'Problème de comportement avec un formateur',
        'Je souhaite signaler un problème concernant l''attitude d''un formateur lors du module de développement web. Son comportement durant les séances est irrespectueux et nuit à l''ambiance d''apprentissage. Je demande une intervention urgente.',
        'in_progress',
        NULL,
        NOW() - INTERVAL 5 DAY,
        NULL,
        NOW() - INTERVAL 5 DAY
    ),

    -- Ticket 3: COMPLETED — resolved
    -- Category: Demandes administratives (1) → Convention de stage (3)
    (
        'TKT-2025-00003',
        2,      -- asaassoukaina
        1,      -- admin
        1,      -- Demandes administratives
        3,      -- Convention de stage
        'request', 'low',
        'Demande de convention de stage — entreprise TechSoft',
        'Bonjour, je sollicite l''établissement pour la préparation et la signature de ma convention de stage avec l''entreprise TechSoft où j''effectuerai mon stage de fin de formation du 1er au 30 mars.',
        'completed',
        NULL,
        NOW() - INTERVAL 10 DAY,
        NOW() - INTERVAL 7 DAY,
        NOW() - INTERVAL 10 DAY
    ),

    -- Ticket 4: REJECTED
    -- Category: Activités & événements (3) → Réservation salle / matériel (10)
    (
        'TKT-2025-00004',
        5,      -- benaliyoussef
        1,      -- admin
        3,      -- Activités & événements
        10,     -- Réservation salle / matériel
        'request', 'low',
        'Réservation salle informatique pour hackathon le weekend',
        'Je souhaite réserver la salle informatique B204 le samedi de 14h à 20h pour organiser un mini-hackathon entre étudiants de la filière développement mobile.',
        'rejected',
        'Les réservations de salles ne sont pas disponibles le weekend. Les salles sont accessibles uniquement du lundi au vendredi de 8h à 18h. Veuillez soumettre une nouvelle demande pour un créneau en semaine.',
        NOW() - INTERVAL 8 DAY,
        NOW() - INTERVAL 6 DAY,
        NOW() - INTERVAL 8 DAY
    ),

    -- Ticket 5: DRAFT — saved by student, not yet submitted
    -- Category: Réclamations techniques (6) → Problème de connexion (16)
    (
        'TKT-2025-00005',
        6,      -- elmansourifatima
        NULL,
        6,      -- Réclamations techniques
        16,     -- Problème de connexion
        'complaint', 'medium',
        'Problème de connexion WiFi dans les salles du bâtiment B',
        'Le signal WiFi dans les salles B201, B202 et B203 est très faible depuis deux semaines. Cela nous empêche d''utiliser les ressources en ligne pendant les cours et ralentit considérablement notre progression.',
        'draft',
        NULL,
        NULL,   -- not submitted yet
        NULL,
        NOW() - INTERVAL 1 DAY
    );

-- =============================================================================
-- SECTION 6: SAMPLE TICKET RESPONSES
--
-- Demonstrates the conversation system.
-- is_internal = 0 → student and admin both see this
-- is_internal = 1 → admin-only internal note (student CANNOT see this)
-- =============================================================================

INSERT INTO `ticket_responses`
    (`ticket_id`, `sender_id`, `message`, `is_internal`, `created_at`)
VALUES

    -- Conversation for Ticket 2 (Grading Dispute — In Progress)
    (
        2, 1,   -- admin responds
        'Thank you for your complaint. We have forwarded it to the academic department for review. You will receive an update within 3 business days.',
        0,      -- public — student can see
        NOW() - INTERVAL 4 DAY
    ),
    (
        2, 1,   -- admin internal note
        'INTERNAL NOTE: Contacted Professor Hassan. He will review the original exam script. Follow up Thursday if no response.',
        1,      -- internal — student CANNOT see
        NOW() - INTERVAL 3 DAY
    ),
    (
        2, 3,   -- chebahanane responds
        'Thank you for the update. I still have my corrected exam paper and can provide a photo as evidence if needed.',
        0,      -- public
        NOW() - INTERVAL 2 DAY
    ),

    -- Conversation for Ticket 3 (Fee Receipt — Completed)
    (
        3, 1,   -- admin responds
        'Your fee receipt is ready. Please collect it from the administrative office (Room A104) with your student ID.',
        0,
        NOW() - INTERVAL 7 DAY
    ),
    (
        3, 2,   -- asaassoukaina responds
        'Thank you! I will come by tomorrow morning to collect it.',
        0,
        NOW() - INTERVAL 6 DAY
    );



-- =============================================================================
-- END OF SEED DATA
-- =============================================================================
