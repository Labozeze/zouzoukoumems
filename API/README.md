# API Zouzoukou Mem's — version finale

Récupérée depuis la conversation DeepSeek « Questions préalables pour base de données commandes ».
Chaque fichier correspond à la **dernière version** proposée dans la conversation.

- URL de base : `https://zouzoukoumems.shop/API`
- Format : JSON, UTF-8
- Authentification : header HTTP `X-API-Key: 0123456789` (et non `HTTP_X_API_KEY`, qui est le nom interne côté PHP)

## Structure

```
API/
├── config/
│   └── database.php            (v1)
├── models/
│   ├── Commande.php            (v1 + getStats() v2 : top canaux, taux, détails journaliers)
│   └── Produit.php             (nouveau)
├── controllers/
│   ├── CommandeController.php  (approche 1 : routeur intégré, recommandée)
│   └── ProduitController.php   (nouveau)
├── sql/
│   └── schema.sql              (tables, triggers, vues, procédure)
├── .htaccess
└── index.php                   (dernière version : clé API + routage commandes/produits)
```

`routes/commandes.php` n'est **pas** nécessaire (DeepSeek a confirmé que le routage est intégré au contrôleur).

## Codes HTTP

| Code | Signification |
|---|---|
| 200 | Succès |
| 201 | Créé |
| 400 | Requête invalide |
| 401 | Clé API absente / invalide |
| 404 | Ressource non trouvée |
| 500 | Erreur serveur |

Erreur 401 :
```json
{ "success": false, "message": "Non autorisé - Clé API invalide" }
```

---

## API Commandes

### 1. Créer une commande — `POST /commandes`

Corps :
```json
{
  "client_nom": "rufin baroan",
  "client_telephone": "0757816452",
  "client_adresse": "Abidjan, Cocody",
  "canal_commande": "whatsapp",
  "mode_paiement": "Mobile Money",
  "articles": [
    { "produit_id": 1, "quantite": 1, "prix_unitaire": 1000 },
    { "produit_id": 2, "quantite": 2, "prix_unitaire": 2000 }
  ]
}
```
Obligatoires : `client_nom`, `client_telephone`, `articles[]` (`produit_id`, `quantite`, `prix_unitaire`).
Optionnels : `client_adresse`, `canal_commande` (whatsapp | mail | direct | autre, défaut direct), `mode_paiement`, `notes`, `statut` (défaut en_attente).

Réponse 201 :
```json
{ "success": true, "message": "Commande créée avec succès", "commande_id": 1, "total": 5000 }
```
Réponse 400 :
```json
{ "success": false, "message": "Données manquantes : client_nom, client_telephone, articles" }
```

### 2. Rechercher des commandes — `GET /commandes` (ou `POST /commandes/search` avec les mêmes champs en JSON)

Filtres : `id`, `telephone` (partiel), `nom` (partiel), `statut`, `date_debut`, `date_fin` (YYYY-MM-DD), `canal`, `page` (défaut 1), `limit` (défaut 10).

Exemple : `GET /commandes?telephone=07578&statut=en_attente&page=1&limit=10`

Réponse 200 :
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "client_nom": "rufin baroan",
      "client_telephone": "0757816452",
      "statut": "en_attente",
      "canal_commande": "whatsapp",
      "total": 5000.00,
      "nombre_articles": 2,
      "articles_detail": "Instants Gravés - MINI x1, Instants Gravés - BEST SELLER x2",
      "date_creation": "2026-04-27 10:30:00"
    }
  ],
  "pagination": { "page": 1, "limit": 10, "total": 1, "pages": 1 }
}
```

### 3. Détail d'une commande — `GET /commandes/{id}`

Réponse 200 :
```json
{
  "success": true,
  "data": {
    "id": 1,
    "client_nom": "rufin baroan",
    "client_telephone": "0757816452",
    "client_adresse": null,
    "canal_commande": "whatsapp",
    "mode_paiement": null,
    "total": 5000.00,
    "statut": "en_attente",
    "date_creation": "2026-04-27 10:30:00",
    "date_modification": "2026-04-27 10:30:00",
    "date_livraison": null,
    "articles": [
      { "id": 1, "produit_id": 1, "nom": "Instants Gravés", "variante": "MINI",
        "quantite": 1, "prix_unitaire_historique": 1000.00, "sous_total": 1000.00 },
      { "id": 2, "produit_id": 2, "nom": "Instants Gravés", "variante": "BEST SELLER",
        "quantite": 2, "prix_unitaire_historique": 2000.00, "sous_total": 4000.00 }
    ],
    "suivi_statuts": [
      { "id": 1, "ancien_statut": null, "nouveau_statut": "en_attente", "date_changement": "2026-04-27 10:30:00" }
    ]
  }
}
```
Réponse 404 :
```json
{ "success": false, "message": "Commande non trouvée" }
```

### 4. Modifier une commande — `PUT /commandes/{id}`

Tous les champs sont optionnels : `client_nom`, `client_telephone`, `client_adresse`, `canal_commande`, `mode_paiement`, `notes`, `articles`.
⚠️ Si `articles` est fourni, toutes les lignes existantes sont remplacées et le total est recalculé.

```json
{ "client_adresse": "Nouvelle adresse", "articles": [ { "produit_id": 1, "quantite": 3, "prix_unitaire": 1000 } ] }
```
Réponse 200 :
```json
{ "success": true, "message": "Commande mise à jour avec succès" }
```
Réponse 400 :
```json
{ "success": false, "message": "Aucune donnée à mettre à jour" }
```

### 5. Changer le statut — `PUT /commandes/{id}/statut`

```json
{ "statut": "confirmee" }
```
Valeurs : `en_attente`, `confirmee`, `en_preparation`, `prete`, `livree`, `annulee`.
Le passage à `livree` renseigne `date_livraison` ; chaque changement est historisé dans `suivi_statuts` (trigger).

Réponse 200 :
```json
{ "success": true, "message": "Statut mis à jour : confirmee" }
```
Réponse 400 :
```json
{ "success": false, "message": "Statut requis" }
```

### 6. Statistiques — `GET /commandes/stats?periode=today|week|month|year`

Réponse 200 (exemple `periode=week`) :
```json
{
  "success": true,
  "data": {
    "total_commandes": "50",
    "chiffre_affaires": "250000.00",
    "panier_moyen": "5208.33",
    "annulations": "2",
    "en_attente": "5",
    "confirmees": "8",
    "en_preparation": "10",
    "pretes": "5",
    "livrees": "20",
    "taux_annulation": 4.00,
    "taux_conversion": 40.00,
    "top_produits": [
      { "nom": "Instants Gravés", "variante": "BEST SELLER", "total_vendus": "30",
        "nombre_commandes": "25", "chiffre_affaires": "60000.00" }
    ],
    "top_canaux": [
      { "canal_commande": "whatsapp", "nombre_commandes": "25", "chiffre_affaires": "125000.00",
        "panier_moyen": "5000.00", "commandes_livrees": "12", "commandes_annulees": "1",
        "pourcentage_ca": "50.00", "pourcentage_commandes": "50.00" }
    ],
    "details_journaliers": [
      { "jour": "2026-04-27", "canal_commande": "whatsapp", "nombre_commandes": "5", "chiffre_affaires": "25000.00" }
    ],
    "canaux_par_statut": [
      { "canal_commande": "whatsapp", "statut": "livree", "nombre": "12" }
    ]
  }
}
```
`details_journaliers` n'est présent que pour `periode=week`.

---

## API Produits

### 1. Lister — `GET /produits` (option `?actif=true|false`, défaut true)

```json
{
  "success": true,
  "data": [
    { "id": 1, "nom": "Instants Gravés", "variante": "MINI", "prix_unitaire": 1000.00,
      "description": null, "actif": true,
      "date_creation": "2026-04-27 10:00:00", "date_modification": "2026-04-27 10:00:00" }
  ],
  "total": 4
}
```

### 2. Rechercher — `GET /produits/search?nom=&variante=&actif=&prix_min=&prix_max=`

Même format de réponse que la liste.

### 3. Détail — `GET /produits/{id}`

```json
{ "success": true, "data": { "id": 1, "nom": "Instants Gravés", "variante": "MINI", "prix_unitaire": 1000.00, "...": "..." } }
```
404 : `{ "success": false, "message": "Produit non trouvé" }`

### 4. Créer — `POST /produits`

```json
{ "nom": "Instants Gravés", "variante": "MAXI", "prix_unitaire": 3000, "description": "Format maximum 100x150mm" }
```
201 : `{ "success": true, "message": "Produit créé avec succès", "produit_id": 5 }`
400 : `Le nom et le prix unitaire sont requis` / `Le prix ne peut pas être négatif`

### 5. Modifier — `PUT /produits/{id}`

```json
{ "prix_unitaire": 3500, "description": "Nouveau prix promo" }
```
200 : `{ "success": true, "message": "Produit mis à jour avec succès" }`

### 6. Désactiver — `DELETE /produits/{id}` (suppression logique, actif = false)

200 : `{ "success": true, "message": "Produit désactivé avec succès" }`

### 7. Réactiver — `PUT /produits/{id}/activate`

200 : `{ "success": true, "message": "Produit activé avec succès" }`

---

## Test rapide (cURL)

```bash
curl -X GET "https://zouzoukoumems.shop/API/commandes/1" -H "X-API-Key: 0123456789"

curl -X POST https://zouzoukoumems.shop/API/commandes \
  -H "Content-Type: application/json" -H "X-API-Key: 0123456789" \
  -d '{"client_nom":"rufin baroan","client_telephone":"0757816452","canal_commande":"direct",
       "articles":[{"produit_id":1,"quantite":1,"prix_unitaire":1000}]}'

curl -X GET "https://zouzoukoumems.shop/API/commandes/stats?periode=month" -H "X-API-Key: 0123456789"

curl -X GET https://zouzoukoumems.shop/API/produits -H "X-API-Key: 0123456789"
```
