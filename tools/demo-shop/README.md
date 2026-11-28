# Demo shop software

A stand-in for a shop's billing software. It provides the API in
[docs/LOCAL-API.md](../../docs/LOCAL-API.md), so the sync agent can be installed and tested end to
end before the real software's developer has built theirs. It is also a working example for that
developer. It is not part of the Laravel app and never runs on the live server.

## Run it

From the project folder, in a terminal you leave open:

```
php -S 127.0.0.1:8765 tools/demo-shop/server.php
```

Each call the agent makes is printed there. In the store's panel, open Shop software sync →
**Billing software connection** and enter:

| Field | Enter |
|---|---|
| API address | `http://localhost:8765/ecom/v1` (the standard address, already filled in) |
| API key | `demo-key` (or what you set in `DEMO_SHOP_KEY` before starting it) |

Then run the installer: it asks only for the setup code.

The shop's data is kept in `tools/demo-shop/data.json`, created from sample data on first use:
an item with a SKU, one without, a T-shirt sold in two variants, and two customers.

## Play the shop

`php tools/demo-shop/shop.php <command>` does what staff do at the counter. Changes reach the
online store on the agent's next cycle (every 5 minutes; restart the agent's service to sync now).

| Command | What it does |
|---|---|
| `list` | Items, stock, customers and web orders |
| `sell ITM-1001 2` | A counter sale; the store shows "Sold in shop" |
| `receive ITM-2040 TEE-BLK-M 5 GRN-2231` | Goods received, with a note |
| `count ITM-1002 35` | A stock count: sets the quantity |
| `price ITM-1001 48` | A new price, including GST |
| `add "Steel Bottle 1 L" 349 BOTTLE-1L 20` | A new item: name, price, SKU, opening stock |
| `dispatch <order number> BlueDart BD123456789` | Ships a web order, with tracking |
| `deliver <order number>` | Marks it delivered |
| `reset` | Back to the sample data |

An item sold in variants takes the variant's SKU after the item id.

## What it does with the agent's calls

As LOCAL-API.md asks: a web order is saved and its stock taken off together, and the same order
number sent again answers `exists`. A cancelled order puts its stock back once. Products and
customers made on the website are found by `external_id`, then SKU (or phone, then email), and
added when new.
