# Intégration OpenWA — Guide Yokalma (WhatsApp notifications)

Documentation à transmettre au développeur Yokalma : créer / démarrer une session, afficher le QR (renouvelé) dans l’app, attendre la connexion, puis envoyer des messages.

---

## 1. Connexion

| Paramètre | Valeur |
|-----------|--------|
| **URL de base** | `https://mywa.tickets-place.net` |
| **Préfixe API** | `/api` |
| **Swagger** | `https://mywa.tickets-place.net/api/docs` |
| **Authentification** | Header `Authorization: Bearer …` **ou** `X-API-Key: …` |
| **Clé API (OPERATOR)** | `owa_k1_e05c74e13e2a679eae14d957458d798979f5a780c3fe36e76284969fd8c3c4b0` |
| **Nom de la clé** | App externe - Yokalma WhatsApp |
| **Session pré-créée** | `yokalma` |
| **Session ID** | `1b9201d2-932d-4cae-8b5f-c58c1d9780a1` |

> Stocker la clé en variable d’environnement (`YOKALMA_OPENWA_API_KEY`), jamais dans le dépôt git.

La clé est **restreinte** à la session `yokalma`. Elle ne peut ni lire ni utiliser la session Tickets Place.

---

## 2. Parcours typique dans ton app

```
1. (optionnel) Créer une session     POST /api/sessions
2. Démarrer la session               POST /api/sessions/{id}/start
3. Afficher le QR (polling ou WS)    GET  /api/sessions/{id}/qr
4. Attendre status === "ready"       GET  /api/sessions/{id}
5. Envoyer des messages              POST /api/sessions/{id}/messages/send-text
```

### Statuts session

| `status` | Signification |
|----------|----------------|
| `created` | Session créée, pas encore démarrée |
| `qr_ready` | QR disponible → à afficher à l’utilisateur |
| `authenticating` | QR scanné, liaison en cours |
| `ready` | Connecté → envois possibles |
| `disconnected` | Déconnecté → éventuellement `start` + rescan |
| `failed` | Échec → `stop` puis `start`, ou rescan |

---

## 3. Créer une session (si besoin)

> La session **`yokalma`** existe déjà. Tu peux l’utiliser directement (section 4).  
> Si tu crées une **nouvelle** session, son UUID ne sera pas dans `allowedSessions` : demander à l’admin d’ajouter l’id à la clé API Yokalma.

```http
POST /api/sessions
Authorization: Bearer {clé}
Content-Type: application/json

{
  "name": "yokalma",
  "config": { "autoReconnect": true }
}
```

**Réponse 201 :**
```json
{
  "id": "1b9201d2-932d-4cae-8b5f-c58c1d9780a1",
  "name": "yokalma",
  "status": "created"
}
```

- `name` : 3–50 caractères, lettres / chiffres / tirets uniquement (`^[a-zA-Z0-9-]+$`)
- Si le nom existe déjà → **409**

---

## 4. Démarrer la session

```http
POST /api/sessions/1b9201d2-932d-4cae-8b5f-c58c1d9780a1/start
Authorization: Bearer {clé}
```

Réponse : objet session (`status` souvent `qr_ready` après quelques secondes).

Si déjà démarrée → **400** `"Session is already started"` → continuer avec le QR / le statut.

---

## 5. Récupérer le QR (renouvellement)

Le QR WhatsApp expire ~toutes les **20 secondes**. OpenWA en génère un nouveau automatiquement : **re-poller** cet endpoint.

```http
GET /api/sessions/1b9201d2-932d-4cae-8b5f-c58c1d9780a1/qr
Authorization: Bearer {clé}
```

**Réponse 200 :**
```json
{
  "qrCode": "data:image/png;base64,iVBORw0KGgo...",
  "status": "qr_ready"
}
```

Afficher `qrCode` directement dans une balise `<img src="…">` (c’est une data URL PNG).

| HTTP | Cas |
|------|-----|
| **200** | QR prêt |
| **400** | Pas encore prêt, ou déjà authentifié (`ready`) |
| **404** | Session inconnue |
| **403** | Clé non autorisée sur cette session |

### Polling recommandé (UI)

```javascript
const BASE = 'https://mywa.tickets-place.net';
const KEY = process.env.YOKALMA_OPENWA_API_KEY;
const SESSION_ID = '1b9201d2-932d-4cae-8b5f-c58c1d9780a1';

async function pollUntilReady(onQr) {
  for (;;) {
    const s = await fetch(`${BASE}/api/sessions/${SESSION_ID}`, {
      headers: { Authorization: `Bearer ${KEY}` },
    }).then(r => r.json());

    if (s.status === 'ready') return s;

    if (s.status === 'qr_ready') {
      const qr = await fetch(`${BASE}/api/sessions/${SESSION_ID}/qr`, {
        headers: { Authorization: `Bearer ${KEY}` },
      });
      if (qr.ok) {
        const { qrCode } = await qr.json();
        onQr(qrCode); // mettre à jour <img src={qrCode}>
      }
    }

    await new Promise(r => setTimeout(r, 3000)); // poll toutes les 3 s
  }
}
```

### Alternative temps réel : WebSocket

```
wss://mywa.tickets-place.net
```

Après connexion socket, envoyer (JSON) :

```json
{
  "type": "subscribe",
  "sessionId": "1b9201d2-932d-4cae-8b5f-c58c1d9780a1",
  "events": ["session.status", "session.qr"]
}
```

Auth : passer la clé API à la connexion (même mécanisme que le dashboard ; souvent query/`auth` selon le client Socket.IO — voir Swagger / `/api/docs`).

Événements utiles :
- `session.qr` → `{ qrCode: "data:image/png;base64,..." }`
- `session.status` → `{ status: "ready" | "qr_ready" | ... }`

### Alternative sans QR : pairing code

```http
POST /api/sessions/{id}/pairing-code
Authorization: Bearer {clé}
Content-Type: application/json

{ "phoneNumber": "2217XXXXXXXX" }
```

Retourne un code à 8 caractères à saisir dans WhatsApp → Appareils liés.

---

## 6. Vérifier que c’est connecté

```http
GET /api/sessions/1b9201d2-932d-4cae-8b5f-c58c1d9780a1
Authorization: Bearer {clé}
```

```json
{
  "id": "1b9201d2-932d-4cae-8b5f-c58c1d9780a1",
  "name": "yokalma",
  "status": "ready",
  "phone": "2217XXXXXXXX",
  "pushName": "Yokalma"
}
```

**N’envoyer que si `status === "ready"`.** Sinon → **409**.

---

## 7. Envoyer un message texte

```http
POST /api/sessions/1b9201d2-932d-4cae-8b5f-c58c1d9780a1/messages/send-text
Authorization: Bearer {clé}
Content-Type: application/json

{
  "chatId": "221785962662",
  "text": "Votre notification Yokalma."
}
```

**Réponse 201 :**
```json
{
  "messageId": "true_203714644189422@lid_3EB0...",
  "timestamp": 1783270317
}
```

### Format `chatId`

| Format | Exemple |
|--------|---------|
| International sans `+` (recommandé) | `221785962662` |
| Avec suffixe WhatsApp | `221785962662@c.us` |

Pas d’espaces, pas de `+`.

### Vérifier qu’un numéro a WhatsApp

```http
GET /api/sessions/{id}/contacts/check/221785962662
Authorization: Bearer {clé}
```

### Exemple cURL

```bash
curl -X POST "https://mywa.tickets-place.net/api/sessions/1b9201d2-932d-4cae-8b5f-c58c1d9780a1/messages/send-text" \
  -H "Authorization: Bearer owa_k1_e05c74e13e2a679eae14d957458d798979f5a780c3fe36e76284969fd8c3c4b0" \
  -H "Content-Type: application/json" \
  -d '{"chatId":"221785962662","text":"Test Yokalma"}'
```

### Exemple Python

```python
import os, requests

BASE = "https://mywa.tickets-place.net"
KEY = os.environ["YOKALMA_OPENWA_API_KEY"]
SESSION_ID = "1b9201d2-932d-4cae-8b5f-c58c1d9780a1"

def send_text(phone: str, text: str) -> dict:
    r = requests.post(
        f"{BASE}/api/sessions/{SESSION_ID}/messages/send-text",
        headers={"Authorization": f"Bearer {KEY}"},
        json={"chatId": phone, "text": text},
        timeout=30,
    )
    r.raise_for_status()
    return r.json()
```

---

## 8. Envoi en masse (optionnel)

```http
POST /api/sessions/{id}/messages/send-bulk
Authorization: Bearer {clé}
Content-Type: application/json

{
  "messages": [
    {
      "chatId": "221785962662",
      "type": "text",
      "content": { "text": "Hello {{name}}" },
      "variables": { "name": "Awa" }
    }
  ],
  "options": {
    "delayBetweenMessages": 3000,
    "randomizeDelay": true,
    "stopOnError": false
  }
}
```

Réponse **202** avec `batchId` + `statusUrl`. Max **100** messages / batch. Suivi :

```http
GET /api/sessions/{id}/messages/batch/{batchId}
```

---

## 9. Autres envois

| Type | Endpoint |
|------|----------|
| Image | `POST .../messages/send-image` |
| Document | `POST .../messages/send-document` |
| Audio | `POST .../messages/send-audio` |
| Localisation | `POST .../messages/send-location` |

Voir Swagger : `/api/docs`.

---

## 10. Stop / restart

```http
POST /api/sessions/{id}/stop
POST /api/sessions/{id}/start
```

Si la session est coincée : `POST /api/sessions/{id}/force-kill` puis `start`.

---

## 11. Erreurs fréquentes

| Code | Cause | Action |
|------|--------|--------|
| **401** | Clé invalide | Vérifier Bearer / X-API-Key |
| **403** | Session hors périmètre de la clé | Utiliser l’id `yokalma` fourni |
| **400** | QR pas prêt / déjà connecté | Retry ou vérifier `status` |
| **409** | Session pas `ready` | Attendre connexion / rescanner |
| **429** | Rate limit | Ralentir |
| **502** | Échec envoi WhatsApp | Retry plus tard |

---

## 12. Checklist dev Yokalma

- [ ] `YOKALMA_OPENWA_API_URL=https://mywa.tickets-place.net`
- [ ] `YOKALMA_OPENWA_API_KEY=owa_k1_e05c74e1…`
- [ ] `YOKALMA_OPENWA_SESSION_ID=1b9201d2-932d-4cae-8b5f-c58c1d9780a1`
- [ ] Écran « Lier WhatsApp » : `start` → poll `qr` → afficher image → poll jusqu’à `ready`
- [ ] Envoi notification : `send-text` seulement si `ready`
- [ ] Gérer déconnexion (`disconnected` → proposer rescanner)

---

## 13. Support

- Dashboard OpenWA : https://mywa.tickets-place.net  
- Swagger : https://mywa.tickets-place.net/api/docs  
- Session Yokalma : `1b9201d2-932d-4cae-8b5f-c58c1d9780a1` (`yokalma`)

---

*Document généré le 2026-08-11 — OpenWA / Yokalma*
