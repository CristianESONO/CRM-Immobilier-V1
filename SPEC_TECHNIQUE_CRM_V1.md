s# SPEC TECHNIQUE — CRM Immobilier V1

**LinkUp Technologies** · Client pilote : GRET INVEST · Cible : SaaS multi-tenant réutilisable
Délai : 6 semaines jusqu'à la mise en production · Dev from scratch

---

## 0. Ce qu'il faut comprendre avant de coder

Ce n'est pas un CRM générique. Quatre fonctions font toute la valeur du produit et sont contractuellement mesurées. **Si le calendrier se tend, on coupe tout le reste, jamais celles-là :**

1. **Traçage obligatoire de la source** — aucun contact ne peut exister sans source. Contrainte base + validation.
2. **Compteur de première réponse en heures ouvrées** — seuil 2 h. Calculé, jamais saisi.
3. **Qualification calculée** — le statut « qualifié » est dérivé de 4 conditions, jamais un champ libre.
4. **Écart à la trajectoire** — comparaison valeur réelle / valeur attendue à la date du jour.

Tout le reste (CRUD contacts, pipeline, listes) est du travail standard : va vite dessus.

---

## 1. Stack

| Couche | Choix | Raison |
|---|---|---|
| Backend | **Laravel 12**, PHP 8.3 | Écosystème, rapidité |
| Admin/UI | **Filament 5** | Génère 70 % des écrans. C'est ce qui rend les 6 semaines tenables. |
| Multi-tenant | **stancl/tenancy** en mode **single database** | Isolation logique par `tenant_id` |
| Rôles | **spatie/laravel-permission** | 4 rôles, voir §3 |
| Audit | **spatie/laravel-activitylog** | Journal non modifiable |
| DB | **PostgreSQL 16** | Contraintes partielles, JSONB, meilleur que MySQL pour les index conditionnels |
| Queue | **Redis + Horizon** | Relances, notifications, imports |
| Scheduler | `schedule:work` via cron | Séquences, détection d'inactivité |
| Mail | SMTP transactionnel (Postmark ou SES) | |
| WhatsApp | **WhatsApp Cloud API** (Meta) | Voir §7 — point de risque n°1 |

**Interdit :** aucun `if ($tenant->id === 1)`, aucune valeur métier en dur, aucun libellé de pipeline dans le code.

---

## 2. Multi-tenant — la décision structurante

**Single database, discriminant `tenant_id` sur toutes les tables métier.** Pas de base par tenant : ça complique les migrations, les rapports transverses et le déploiement, pour un bénéfice nul à notre volumétrie (< 50 000 contacts/tenant).

### Implémentation
```php
// app/Models/Concerns/BelongsToTenant.php
trait BelongsToTenant {
    protected static function bootBelongsToTenant(): void {
        static::addGlobalScope(new TenantScope);
        static::creating(fn ($m) => $m->tenant_id ??= tenant('id'));
    }
}
```

- Global scope appliqué **au niveau du modèle**, jamais requête par requête.
- Résolution du tenant par sous-domaine : `gretinvest.crm.linkup.sn`.
- `tenant_id` **NOT NULL** + index composite en tête de chaque index métier : `(tenant_id, created_at)`, `(tenant_id, status)`.

### Test obligatoire (bloquant pour la recette)
Un test qui crée 2 tenants, se connecte comme user du tenant A et vérifie qu'aucune route (y compris par manipulation d'ID dans l'URL) ne retourne une ressource du tenant B. Ce test tourne en CI et ne doit jamais être désactivé.

### Paramétrage en données, pas en code
Table `tenant_settings` (JSONB) contenant : étapes du pipeline, libellés, motifs de perte, nomenclature des sources, heures ouvrées, seuil de première réponse, modèles de messages, thème (logo, couleur primaire). Le prochain client n'aura pas le même pipeline.

---

## 3. Rôles

| Rôle | Portée |
|---|---|
| `super_admin` | LinkUp. Cross-tenant. Accès support **tracé** dans l'activity log. |
| `admin` | Direction client. Tout le tenant + paramétrage. |
| `commercial` | Ses contacts assignés. Création, qualification, pipeline. |
| `observer` | **Agrégats uniquement.** Aucun accès aux données nominatives — c'est le rôle de l'agence de com, et c'est ce qui nous évite un NDA tripartite. |

Le rôle `observer` doit être implémenté par des endpoints/vues séparés, pas par un simple masquage d'affichage.

---

## 4. Modèle de données

```sql
tenants (id, slug, name, domain, settings jsonb, created_at)

users (id, tenant_id, name, email, password, role, is_active)

contacts (
  id, tenant_id,
  first_name, last_name, phone_e164, email, preferred_channel, language,
  country, city, is_diaspora boolean,
  property_type, district, budget_min, budget_max, decision_horizon, purpose,
  source_id NOT NULL, sub_source, referrer_id, utm_source, utm_medium, utm_campaign, landing_page,
  assigned_to, status, potential_score,
  -- qualification (4 conditions, chacune horodatée)
  q_replied_at, q_project_at, q_budget_at, q_source_at,
  qualified_at,                    -- calculé, jamais écrit à la main
  -- compteur 2h
  first_response_at, first_response_minutes int,
  last_activity_at, next_action_at,
  consent_at, consent_source,
  created_at, updated_at
)
-- UNIQUE (tenant_id, phone_e164) WHERE phone_e164 IS NOT NULL
-- UNIQUE (tenant_id, email) WHERE email IS NOT NULL
-- INDEX (tenant_id, status), (tenant_id, assigned_to, next_action_at)

sources (id, tenant_id, channel, label, is_active)   -- 5 canaux, paramétrable
referrers (id, tenant_id, name, type, organisation, phone, email)

status_history (id, tenant_id, contact_id, from_status, to_status, reason, user_id, changed_at)
-- source de vérité des taux de passage. On ne recalcule JAMAIS depuis contacts.status

activities (id, tenant_id, contact_id, user_id, type, channel, body, occurred_at)

properties (id, tenant_id, name, location, property_type, price_min, price_max,
            delivery_date, status, landing_page_url)
units (id, tenant_id, property_id, reference, area, price, status)
contact_property (contact_id, property_id, interest_level)

sequences (id, tenant_id, key, name, trigger, steps jsonb, is_active)
sequence_enrollments (id, tenant_id, contact_id, sequence_id, current_step,
                      next_run_at, status, enrolled_at, stopped_at, stop_reason)
message_log (id, tenant_id, contact_id, channel, template, provider_id,
             status, sent_at, delivered_at, error)
```

**Règle :** `phone_e164` normalisé à l'insertion via `giggsey/libphonenumber-for-php`, région par défaut `SN`. Sans ça la déduplication ne marche pas (77 123 45 67 / +221771234567 / 00221771234567).

---

## 5. Les 4 fonctions différenciantes — détail d'implémentation

### 5.1 Source obligatoire
- Colonne `source_id` **NOT NULL** en base (pas seulement en validation applicative).
- S'applique aussi à la saisie manuelle et à l'import CSV. Aucune exception, aucun « source par défaut ».
- Capture des UTM depuis le referrer du formulaire ; pour WhatsApp, message pré-rempli différencié par page (voir §7).

### 5.2 Compteur de première réponse
```php
// Démarré à la création, arrêté à la première activité sortante (ou passage "Contacté")
$minutes = BusinessHours::forTenant($tenant)->diffInMinutes($contact->created_at, $respondedAt);
```
- **Heures ouvrées**, pas heures calendaires. Calendrier paramétrable par tenant (jours + plage horaire + fuseau `Africa/Dakar`).
- Le cas qui casse toujours : un contact arrivé vendredi 18h répondu lundi 9h → **1 h ouvrée**, pas 63 h. Écris le test en premier.
- Job planifié toutes les 15 min : alerte si un contact dépasse le seuil sans réponse.
- Agrégat exposé en moyenne **et médiane** (la moyenne se fait détruire par un seul outlier).

### 5.3 Qualification calculée
```php
// Observer sur Contact — jamais un setter public
$contact->qualified_at = ($contact->q_replied_at && $contact->q_project_at
    && $contact->q_budget_at && $contact->q_source_at)
    ? ($contact->qualified_at ?? now())
    : null;
```
- Aucun endpoint, aucune action Filament ne peut écrire `qualified_at` directement.
- Chaque décochage est tracé dans l'activity log. C'est ce qui rend le chiffre de 150 défendable en revue de fin de POC : on peut prouver qu'aucun contact tiède n'a été requalifié à la main.

### 5.4 Écart à la trajectoire
Table `targets (tenant_id, metric, month, expected_cumulative)`. Trajectoire de référence GRET INVEST :

| Mois | 2 | 3 | 4 | 5 | 6 |
|---|---|---|---|---|---|
| Qualifiés cumulés | 15 | 40 | 70 | 115 | 150 |
| Visites du mois | 1 | 2 | 3 | 9 | 5 |

Le dashboard affiche **réel vs attendu au prorata du jour courant**, pas seulement la cible finale. C'est ce qui permet de corriger en semaine 2 au lieu du comité suivant.

---

## 6. Pipeline

`nouveau → contacte → qualifie → rdv_planifie → visite_planifiee → visite_realisee → proposition → gagne | perdu`

- Statuts **stockés dans `tenant_settings`**, pas en enum PHP.
- Chaque transition écrit dans `status_history`. Les taux de passage se calculent uniquement depuis cette table.
- `perdu` exige un motif (liste fermée paramétrable).
- Job quotidien : contact sans activité depuis 45 j → enrôlement séquence `reactivation`. Après 3 relances sans réponse → sortie du pipeline actif (**jamais de suppression**).

---

## 7. Intégrations

### WhatsApp Cloud API — risque n°1 du projet
Compte Business Meta + numéro vérifié + **templates soumis à validation Meta (délai variable, parfois plusieurs jours)**. À lancer en **semaine 1**, avant tout code. Si la validation traîne, le fallback est SMS + email pour les séquences, WhatsApp restant en canal manuel.

- Webhook entrant → création de contact + capture de la source depuis le message pré-rempli.
- Message pré-rempli par page : `Bonjour, je suis intéressé par [PROGRAMME] (réf. web-lp-almadies)` — le suffixe est la seule façon de tracer un contact WhatsApp.
- Fenêtre de 24 h : hors fenêtre, seuls les templates approuvés passent. À gérer explicitement dans le moteur de séquences.

### Formulaires site
Endpoint `POST /api/v1/leads` authentifié par clé API par tenant. Rate limiting + honeypot.

### Email
Webhooks de remise → `message_log`. Lien de désinscription obligatoire sur tout envoi automatisé.

---

## 8. Moteur de séquences

Steps en JSONB : `[{"delay_hours": 48, "channel": "whatsapp", "template": "relance_1"}, ...]`

- Job `ProcessSequenceStep` dispatché par le scheduler sur `next_run_at`.
- **Toute réponse entrante du contact arrête immédiatement la séquence** (`status = stopped`, `stop_reason = replied`). C'est le bug le plus embarrassant possible : relancer quelqu'un qui vient de répondre.
- Idempotence : un step ne s'exécute qu'une fois même si le job est rejoué.
- 3 séquences V1 : `nouveau_contact` (J+2, J+5, J+12), `diaspora`, `reactivation`.

---

## 9. Conformité (loi sénégalaise 2008-12)

- `consent_at` + `consent_source` renseignés à la collecte.
- Export intégral d'un contact (JSON) et suppression sur demande, **déclenchables par l'admin client sans nous** — c'est une obligation contractuelle (article 11).
- Export global CSV/JSON du tenant, idem.
- Chiffrement en transit (TLS) et au repos. Sauvegarde quotidienne, rétention 30 j, **restauration testée au moins une fois pendant le POC** (une sauvegarde jamais restaurée n'est pas une sauvegarde).
- Activity log immuable : création, changement de statut, modification de qualification, export, suppression.

---

## 10. Ce qu'on ne fait PAS en V1

Scoring IA · portail prescripteur en libre-service · signature électronique · app mobile native · visites virtuelles intégrées · gestion locative · facturation · sync bidirectionnelle d'agenda · module de biens élaboré (le référentiel reste volontairement léger : le CRM suit des acheteurs, pas un patrimoine).

---

## 11. Planning

| S | Livrable |
|---|---|
| **S1** | Repo, CI, socle Laravel + Filament + tenancy, auth, rôles, migrations complètes. **Lancer la validation WhatsApp Business dès le jour 1.** |
| **S2** | CRUD contacts, normalisation téléphone, déduplication, import CSV avec mapping et prévisualisation |
| **S3** | Endpoint leads, webhooks WhatsApp + email, traçage des sources, capture UTM |
| **S4** | Pipeline, `status_history`, qualification calculée, référentiel biens |
| **S5** | Moteur de séquences, compteur 2 h, notifications, modèles de messages |
| **S6** | Dashboard, exports, conformité, recette, formation (2 h) |

---

## 12. Definition of Done

Non négociable, chaque item est un critère de recette :

- [ ] Test d'isolation cross-tenant vert en CI
- [ ] Création sans source **refusée par la base**
- [ ] Test du compteur 2 h sur un cas chevauchant un week-end
- [ ] `qualified_at` non écrivable hors observer
- [ ] Séquence stoppée par une réponse entrante
- [ ] Export intégral produit par l'admin client sans intervention LinkUp
- [ ] Rapport PDF de comité exportable sur période choisie
- [ ] Restauration de sauvegarde réalisée et documentée
- [ ] Aucun libellé métier ni règle client en dur dans le code
- [ ] Liste de 5 000 contacts affichée en < 2 s

---

## 13. Note d'architecture — à produire au démarrage

Le contrat cède au client les **paramétrages** du CRM (article 10.1) et nous réserve nos **briques génériques préexistantes** (article 10.2). Concrètement :

- Le **moteur** (tenancy, séquences, compteur, qualification, dashboard) est un socle LinkUp, versionné dans un dépôt distinct du paramétrage client.
- Le **paramétrage GRET INVEST** (settings JSONB, templates, trajectoire, thème) est ce qui leur est cédé.

Cette séparation doit être **effective dans l'organisation du code**, pas seulement affirmée. Sans elle, on vend le produit au prix d'un lot.

---

*LinkUp Technologies · RCCM SN.DKR.2026.B.18773 · contact@linkup.sn*
