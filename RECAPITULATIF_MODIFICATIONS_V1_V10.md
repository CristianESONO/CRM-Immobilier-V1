# 📜 Récapitulatif Exhaustif des Modifications & Architecture (V1 à V10)

Ce document récapitule l'ensemble des évolutions, briques logicielles, schémas de base de données, services métier et suites de tests mis en place sur le **CRM & Core Transactionnel Immobilier VEFA Multi-Tenant**.

---

## 🏗️ 1. Vue d'Ensemble de l'Architecture (V1 à V10)

```text
                    ┌──────────────────────┐
                    │      Filament        │
                    │    Back-office       │
                    └──────────┬───────────┘
                               │
                               ▼
┌──────────────┐       ┌──────────────────────┐       ┌──────────────────────┐
│ Portail      │──────▶│   Domain Services    │──────▶│ WhatsApp Gateway     │
│ Client       │       │                      │       │ (OpenWA QR & Meta)   │
└──────────────┘       │ Reservation          │       └──────────────────────┘
┌──────────────┐       │ Payment              │       ┌──────────────────────┐
│ Portail      │──────▶│ Contract             │──────▶│ Data Governance      │
│ Partenaire   │       │ Commission           │       │ & SaaS Provisioning  │
└──────────────┘       │ Workflow             │       └──────────────────────┘
┌──────────────┐       └──────────┬───────────┘
│ API v1       │───────────────────┤
└──────────────┘                   │
                                   ▼
                         ┌─────────────────┐
                         │ Domain Events   │
                         │ (UUID & Corr.)  │
                         └────────┬────────┘
                                  │
             ┌────────────────────┼────────────────────┐
             ▼                    ▼                    ▼
       Workflow Engine      Webhook Engine       Audit Log
             │             (HMAC SHA-256)
             ▼                    ▼
       Notifications        Systèmes tiers
```

---

## 📅 2. Synthèse Détaillée par Jalon Fonctionnel

### 🔹 V1 — CRM / Prospects & Moteur SLA
- **Gestion des Leads & Contacts** : Qualification selon 4 critères obligatoires.
- **Calculateur SLA Heures Ouvrées** : Prise en compte des week-ends pour l'incrémentation des délais de prise en charge.

### 🔹 V2 — Core Transactionnel & Échéanciers VEFA
- **Réservation de Lots (`Reservation`)** : Verrouillage dynamique des lots (`Unit`) sous transactions pessimistes (`lockForUpdate()`).
- **Génération d'Échéancier VEFA** : Calcul automatique des appels de fonds par phase de construction.
- **Enregistrement des Paiements (`Payment`)** : Validation du solde, gestion des acomptes et génération des reçus.

### 🔹 V3 — Contractuel, Documents & Signature Électronique
- **Contrats & Versioning (`Contract`, `ContractVersion`)** : Génération de PDF certifiés avec checksum SHA-256 sur disque sécurisé.
- **Gestion KYC Client (`BuyerDocument`)** : Validation contextuelle des pièces d'identité et documents d'entreprise.
- **Intégration Signature eIDAS** : Webhooks de confirmation de signature avec en-têtes HMAC et protection contre le rejeu.

### 🔹 V4 — Intelligence & Alertes Compliances (V4.1 à V4.4)
- **V4.1 Sales Intelligence** : Métriques de vélocité commerciale et entonnoir de conversion.
- **V4.2 Stock Intelligence** : Calcul du prix/m² pondéré $\frac{\sum \text{Prix}}{\sum \text{Surface}}$, âge commercial (`marketed_at`) et classification des stocks.
- **V4.3 Finance Intelligence** : Encaissements, projections de trésorerie et créances en retard.
- **V4.4 Operational & Compliance Alerts (`OperationalAlert`)** : Détection automatique des retards contractuels et anomalies KYC.

### 🔹 V5 — Workflow Engine & Automatisation (V5.1 à V5.3)
- **V5.1 Moteur de Workflows & Déclencheurs Événementiels** : Modélisation complète (`Workflow`, `WorkflowTrigger`, `WorkflowCondition`, `WorkflowAction`, `WorkflowExecution`, `WorkflowExecutionStep`), écouteurs d'événements de domaine (`ReservationCreated`, `PaymentRecorded`, `ContactCreated`) et dispatch asynchrone des actions avec délais (`ExecuteWorkflowJob`).
- **V5.2 Hardening & Résilience Concurrente** : Protection anti double-booking sur les réservations via verrouillage pessimiste transactionnel (`lockForUpdate()`), et système de snapshot d'urgence / restauration isolée par tenant.
- **V5.3 Moteur de Séquences & Relances Intelligentes** : Enrôlement automatique de séquences de relance (`ProcessSequences`), interruption immédiate en cas de réponse du prospect (`stop_reason = replied`) pour éviter tout sur-démarchage, et commande planifiée de contrôle des alertes SLA (`CheckFirstResponseAlerts`).

### 🔹 V6 — Portails, Communication Center & Passerelle WhatsApp (V6.1 à V6.5)
- **V6.1 Portail Client Acquéreur** : Suivi de chantier, téléchargement des contrats et suivi des paiements.
- **V6.2 Portail Partenaire / Prescripteur** : Soumission de prospects et suivi des commissions.
- **V6.3 Communication Center** : Préférences de communication (Email, SMS, WhatsApp) et historique des messages expédiés.
- **V6.4 API Publique `/api/v1/`** : Endpoints REST sécurisés par tenant avec rate limiting et tokens dédiés.
- **V6.5 Passerelle WhatsApp Opérationnelle (OpenWA & Meta Cloud API)** :
  - **OpenWA Gateway (Passerelle YokAlma)** : Page d'administration Filament dédiée (`ManageWhatsAppSession`) avec appairage par scan de **QR Code en temps réel** (rafraîchi toutes les 20s), polling du statut (`created`, `qr_ready`, `authenticating`, `ready`, `disconnected`), et interface d'envoi de tests directs.
  - **Meta WhatsApp Cloud API & Webhooks entrants** : `WhatsAppWebhookController` pour la réception instantanée des messages prospects, la capture des sources pré-remplies par programme immobilier et le déclenchement des arrêts de séquence.

### 🔹 V7 — Écosystème Partenaires & Commission Engine (V7.1 à V7.7)
- **V7.1 - V7.5 Cycle de Vie Stricte** : `calculated` $\rightarrow$ `validated` $\rightarrow$ `payable` $\rightarrow$ `paid`.
- **V7.3 Partner Attribution** : Fenêtre d'attribution glissante de **90 jours**.
- **V7.6 Hachage SHA-256** : Sécurisation des jetons d'accès portails (`portal_token_hash`).
- **V7.7 Financial Hardening** : Snapshots du taux et du nom de règle (`rate_snapshot`, `rule_name_snapshot`), traçabilité du payeur (`paid_by_user_id`), mode de paiement (`payment_method`), et **immutabilité absolue de l'état `paid`**.

### 🔹 V8 — API Platform, Outbound Webhooks & Observabilité (V8.1 à V8.4)
- **V8.1 API Platform** : Clés d'API hachées (`ApiKey`), scopes granulaires (`leads:read`, `properties:read`, `*`), en-tête d'idempotence `X-Idempotency-Key` (24h).
- **V8.2 Outbound Webhook Engine** : Subscriptions, livreurs asynchrones (`DispatchOutboundWebhookJob`), signatures **HMAC SHA-256** (`X-CRM-Signature`), et stratégie de retry à backoff exponentiel.
- **V8.3 Connecteurs Externes** : Abstractions `PaymentProviderInterface`, `SignatureProviderInterface`, `NotificationProviderInterface` (avec implémentation WhatsApp hybride).
- **V8.4 Observabilité Applicative** : Endpoint `GET /api/v1/metrics` restituant la santé du système, les performances webhooks et la profondeur de file d'attente.

### 🔹 V9 — Platform Governance & Integration Control (V9.1 à V9.5)
- **V9.1 Event Governance** : Interface `DomainEventInterface` injectant `event_id` (UUID `evt_...`), `correlation_id` (`corr_...`) et `schema_version` (`1.0`) dans tous les événements et webhooks.
- **V9.2 Webhook Replay Engine** : Admin Filament `WebhookSubscriptionResource` et méthode `replayDelivery()` pour réexpédier manuellement les livraisons en échec.
- **V9.3 API Governance** : Admin Filament `ApiKeyResource` et révocation instantanée via `revoked_at`.
- **V9.4 Audit Trail & Supervision des Intégrations (Dead-Letter Queue)** : Surveillance des livraisons en échec définitif (`failed`), traçabilité immuable des actions sensibles d'administration et alertes sur dépassement du seuil de rejeu.
- **V9.5 Data Governance RGPD** : `DataGovernanceService` avec anonymisation PII irréversible des contacts et export complet des données tenant.

### 🔹 V10 — Industrialisation SaaS & Multi-Tenant
- **Offres Tarifaires & Quotas** : Plans `starter`, `pro`, `enterprise` avec limites configurables (`max_users`, `max_properties`).
- **Provisionnement Automatisé** : `TenantProvisioningService::provisionTenant()` avec activation dynamique des feature flags (`advanced_analytics`, `custom_webhooks`, `dedicated_api`) et contrôle strict des quotas (`checkLimit()`).

---

## 🗄️ 3. Historique des Migrations de Base de Données

| Migration | Rôle & Description |
| :--- | :--- |
| `0001_01_01_000000_create_users_table.php` | Utilisateurs et sessions Laravel |
| `2026_01_01_000001_create_crm_tables.php` | Tenants, Sources, Contacts, Properties, Units |
| `2026_01_01_000002_create_opportunities_table.php` | Opportunités et pipeline commercial |
| `2026_01_01_000003_create_reservations_and_payments_tables.php` | Réservations, Échéanciers VEFA et Paiements |
| `2026_01_01_000004_create_payment_reminders_table.php` | Relances de paiement automatisées |
| `2026_01_01_000005_add_idempotency_and_status...php` | Clés d'idempotence sur les relances |
| `2026_01_01_000006_create_audit_logs_table.php` | Traçabilité et journaux d'audit système |
| `2026_01_01_000007_create_refunds_table.php` | Remboursements et annulations |
| `2026_01_01_000008_create_contracts_and_documents_tables.php` | Contrats, Versions et Pièces KYC |
| `2026_01_01_000010_add_typology_and_marketed_at...php` | Typologies et âge commercial des lots |
| `2026_01_01_000011_create_operational_alerts_table.php` | Alertes de compliance et opérationnelles |
| `2026_01_01_000012_create_workflows_tables.php` | Moteur de Workflows et exécutions |
| `2026_01_01_000013_create_buyer_and_partner_portals_table.php` | Accès sécurisés aux Portails Client & Partenaire |
| `2026_01_01_000014_create_communication_preferences_table.php` | Préférences et journaux de communication |
| `2026_01_01_000015_create_partner_management...php` | Apporteurs d'affaires, Conventions et Commissions |
| `2026_01_01_000016_add_rule_snapshots...php` | Snapshots de règles, traçabilité payeur et immutabilité |
| `2026_01_01_000017_create_outbound_webhooks_tables.php` | API Keys, Webhook Subscriptions et Deliveries |
| `2026_01_01_000018_create_saas_and_governance_tables.php` | Révocation API Keys, Plans SaaS et Quotas Tenants |

---

## 🛠️ 4. Principaux Services Métier Délégués

- `ReservationService` : Gestion atomique des réservations et des verrous de stock.
- `CommissionEngineService` : Calcul, validation, passage en payable et règlement immuable des commissions.
- `PartnerAttributionService` : Attribution glissante sur 90 jours entre prospect et apporteur.
- `OutboundWebhookDispatcherService` : Dispatching, signature HMAC SHA-256 et rejeu manuel de webhooks.
- `OpenWaService` : Communication avec la passerelle WhatsApp OpenWA (YokAlma), gestion des sessions QR Code et envoi de messages directs.
- `WhatsAppService` : Passerelle Meta WhatsApp Cloud API et gestion de fallback multicanal.
- `PaymentReminderService` : Moteur de génération des relances d'échéances VEFA par WhatsApp / SMS / Email.
- `AppObservabilityService` : Restitution des métriques métier, d'intégration et techniques.
- `DataGovernanceService` : Anonymisation PII RGPD et export d'archive tenant.
- `TenantProvisioningService` : Provisionnement SaaS, feature flags et contrôle des quotas.

---

## 🧪 5. Validation de la Suite de Tests (114 tests, 551 assertions, 0 échec)

| Suite de Tests Feature | Couverture & Fonctionnalités Certifiées | Statut |
| :--- | :--- | :---: |
| `CoreBusinessRulesTest` | SLA, qualification des leads, règles métier de base | ✅ PASS |
| `ReservationAndPaymentTest` | Verrouillage atomique, appels de fonds, encaissements | ✅ PASS |
| `ContractAndDocumentTest` | Versioning contrat, checksum SHA-256, validation KYC | ✅ PASS |
| `SalesAnalyticsTest` | KPI de vélocité commercial, conversion par étape | ✅ PASS |
| `StockAnalyticsTest` | Prix/m² pondéré, ancienneté du stock, santé du stock | ✅ PASS |
| `FinanceAnalyticsTest` | Cash-flow, encaissements, créances en retard | ✅ PASS |
| `OperationalAlertTest` | Génération et résolution automatique d'alertes SLA/KYC | ✅ PASS |
| `WorkflowEngineTest` | Déclenchement automatique et exécution de workflows | ✅ PASS |
| `CustomerAndPartnerPortalTest` | Accès portails, tokens hachés SHA-256 | ✅ PASS |
| `CommunicationCenterTest` | Choix des canaux et respect des préférences client | ✅ PASS |
| `PartnerManagementAndCommissionTest` | Calcul commission, attribution 90j, transitions d'état | ✅ PASS |
| `ApiPlatformAndWebhooksTest` | Invariants V7, Scopes V8.1, Signatures HMAC V8.2, Métriques V8.4 | ✅ PASS |
| `PlatformGovernanceAndSaasTest` | Event UUID V9.1, Replay V9.2, RGPD V9.5, SaaS Quotas V10 | ✅ PASS |
| `ProductionCertificationTest` | Transactions concurrentes et sauvegarde/restauration tenant | ✅ PASS |
| `ProductionHardeningTest` | Tests aux limites (montants négatifs, doublons, idempotence) | ✅ PASS |
| `ProductionReadinessContractTest` | Génération PDF binaire, webhooks eIDAS et téléchargeurs | ✅ PASS |

```text
  Tests:    114 passed (551 assertions)
  Duration: 11.49s
```

---

## 🔑 6. Matrice des Comptes, Identifiants & Habilitations

### A. Comptes Back-Office Administration & Exploitation (Filament Admin)

| Rôle Utilisateur | Nom du Compte | Adresse E-mail | Mot de Passe | Portée / Tenant | Responsabilités & Droits Métier |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 🛡️ **Super Admin** | Super Admin LinkUp | `superadmin@linkup.sn` | `Password123!` | Global (Multi-Tenant) | Supervision globale SaaS (V10), provisionnement de nouveaux tenants, gestion des forfaits et quotas, observabilité technique (`/api/v1/metrics`), gestion globale des API Keys et webhooks. |
| 👑 **Admin Promoteur** | Admin GRET INVEST | `admin@gretinvest.sn` | `Password123!` | Tenant (GRET INVEST) | Administration complète du tenant : validation des contrats, validation KYC acquéreurs, validation et paiement des commissions apporteurs (`paid`), configuration des workflows V5, gestion des API Keys V8.1, replay des webhooks V9.2 et anonymisation RGPD V9.5. |
| 💼 **Commercial** | Commercial GRET INVEST | `commercial@gretinvest.sn` | `Password123!` | Tenant (GRET INVEST) | Prise en charge et qualification des leads (SLA 2h), gestion des opportunités, réservation de lots avec verrouillage atomique (V2), enregistrement des acomptes/paiements et émission d'échéanciers VEFA. |
| 👁️ **Observateur** | Observateur Agence Com | `observer@agence-com.sn` | `Password123!` | Tenant (GRET INVEST) | Accès en **lecture seule** aux tableaux de bord d'analyse (Sales Intelligence V4.1, Stock Intelligence V4.2, Finance Intelligence V4.3, Operational Alerts V4.4) sans droit de mutation transactionnelle. |

---

### B. Portails Externes & Jetons de Connexion Sécurisés

| Portail Externe | URL d'Accès | Méthode d'Authentification | Jetons & Sécurité | Fonctionnalités Disponibles |
| :--- | :--- | :--- | :--- | :--- |
| 🏠 **Portail Client Acquéreur** | `http://localhost:8000/portal/client` | E-mail Acquéreur ou Réf. Réservation | Jeton d'accès haché SHA-256 (`buyer_portal_accesses`) | Suivi de l'avancement des travaux VEFA, téléchargement des contrats certifiés PDF (V3), consultation du solde des paiements et soumission des pièces KYC. |
| 🤝 **Portail Partenaire Prescripteur** | `http://localhost:8000/portal/partner` | E-mail Apporteur ou N° Convention | Jeton d'accès haché SHA-256 (`partner_portal_accesses`) | Soumission de prospects avec attribution 90 jours (V7.3), suivi du cycle de vie des commissions (`calculated` $\rightarrow$ `paid`), enregistrement des RIB et avis de virement. |

---

### C. Sécurisation de la Navigation Back-Office par Rôle (`shouldRegisterNavigation`)

Pour garantir un cloisonnement strict dès l'interface utilisateur, la visibilité des ressources et pages Filament est régie au niveau du code (commit `b84a071`) :

- **Commerciaux (`commercial`)** : Accès restreint au module `ContactResource` (leurs contacts assignés). Les menus `PropertyResource`, `SourceResource`, `WorkflowResource`, `ApiKeyResource` et la passerelle WhatsApp leur sont strictement masqués.
- **Administrateurs (`admin`, `super_admin`)** : Visibilité intégrale sur le référentiel immobilier, la configuration des sources de leads, les clés API, les abonnements webhooks et la gestion des sessions WhatsApp OpenWA (`ManageWhatsAppSession`).
- **Observateurs (`observer`)** : Masquage total des formulaires et tables opérationnelles pour réserver l'accès aux seuls tableaux de bord décisionnels agrégés (Sales, Stock, Finance Intelligence).

---

## 🚀 7. Feuille de Route Déploiement & Infrastructure de Production

Ce chapitre formalise les prérequis d'infrastructure, de pipeline et d'exploitation nécessaires au passage en production réelle du CRM :

### 1. Base de Données Relationnelle (PostgreSQL 16)
- **Moteur de Production** : Migration de l'environnement SQLite de développement vers **PostgreSQL 16**.
- **Contraintes & Indexation** : Index partiels conditionnels `UNIQUE (tenant_id, phone_e164) WHERE phone_e164 IS NOT NULL`, colonnes `JSONB` pour les paramètres et étapes de workflows, index composites avec discriminant `tenant_id` en tête.
- **Variables Serveur** : Fichier `.env.example` documenté pour la connexion `pgsql` (`DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

### 2. Gestionnaire de Files Asynchrones (Redis + Laravel Horizon)
- **Traitement Asynchrone** : Délégation des jobs lourds (`DispatchOutboundWebhookJob`, `ExecuteWorkflowJob`, relances multicanales).
- **Files Dédiées** : Séparation des priorités (`high`, `default`, `webhooks`, `notifications`).
- **Supervision** : Interface Laravel Horizon pour le monitoring des débits, la latence des queues et la relance manuelle des jobs en échec.

### 3. Ordonnanceur Système (Cron Daemon)
- **Exécution Périodique** : Ajout du cron système sur le serveur hôte :
  ```bash
  * * * * * cd /var/www/crm && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Commandes Métier Automatisées** :
  - `php artisan sla:check-alerts` : Détection toutes les 15 minutes des dépassements de SLA (première réponse > 2h ouvrées).
  - `php artisan sequences:process` : Traitement des étapes de relance des contacts avec arrêt immédiat dès réception d'une réponse.

### 4. Intégrations WhatsApp en Production (Stratégie Hybride)
- **Passerelle OpenWA (YokAlma)** : Service Node.js/Puppeteer conteneurisé gérant les sessions WhatsApp Web, avec maintien des sessions `autoReconnect` et appairage QR code via l'admin Filament.
- **WhatsApp Cloud API (Meta)** : Numéro de secours vérifié Meta Business et validation préalable des templates de relance pour la délivrabilité hors fenêtre conversationnelle de 24h.
- **Webhook Ingestion** : Endpoint `/api/webhooks/whatsapp` sous protection CSRF exemptée, capturant les messages entrants et assurant la déduplication instantanée.

### 5. Pipeline d'Intégration & Déploiement Continu (CI/CD)
- **GitHub Actions** :
  - Étape 1 : Validation de syntaxe et respect des standards PSR-12 (`php-cs-fixer`).
  - Étape 2 : Exécution automatique des **114 tests automatisés** (551 assertions).
  - Étape 3 : Exécution bloquante du test d'étanchéité multi-tenant (`TenantIsolationTest`).
- **Déploiement Zéro-Downtime** : Utilisation d'outils de release atomique (Envoy, Deployer ou conteneurs Docker) avec bascule de symlink après exécution de `php artisan migrate --force` et `php artisan config:cache`.




