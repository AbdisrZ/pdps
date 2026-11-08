| Direktori | Statement baseline | Statement sesudah | Δ | Method baseline | Method sesudah | Δ |
|---|---|---|---|---|---|---|
| `CartRule/src/Helpers` | 23/238 (9.66%) | 23/238 (9.66%) | +0.00 pp | 3/12 (25.00%) | 3/12 (25.00%) | +0.00 pp |
| `Checkout/src` | 454/885 (51.30%) | 502/885 (56.72%) | +5.42 pp | 48/81 (59.26%) | 51/81 (62.96%) | +3.70 pp |
| `Product/src/Type` | 458/1625 (28.18%) | 509/1625 (31.32%) | +3.14 pp | 36/158 (22.78%) | 38/158 (24.05%) | +1.27 pp |
| `Shop/src/Http/Controllers/API` | 156/669 (23.32%) | 218/669 (32.59%) | +9.27 pp | 7/54 (12.96%) | 11/54 (20.37%) | +7.41 pp |
| `Shop/src/Http/Requests` | 71/79 (89.87%) | 71/79 (89.87%) | +0.00 pp | 18/21 (85.71%) | 18/21 (85.71%) | +0.00 pp |
| **Total** | 1162/3496 (**33.24%**) | 1323/3496 (**37.84%**) | +4.61 pp | 112/326 (34.36%) | 121/326 (37.12%) | +2.76 pp |

Checkout/src tanpa Database/: 413/619 (66.72%) -> 461/619 (74.47%), +7.75 pp

| Method | Baseline | Sesudah | Δ |
|---|---|---|---|
| `Checkout/src/Cart.php::addProduct()` | 19/29 (65.52%) | 22/29 (75.86%) | +10.34 pp |
| `Checkout/src/Cart.php::removeItem()` | 8/9 (88.89%) | 9/9 (100.00%) | +11.11 pp |
| `Checkout/src/Cart.php::updateItems()` | 25/31 (80.65%) | 29/31 (93.55%) | +12.90 pp |
| `Checkout/src/Cart.php::setCouponCode()` | 0/3 (0.00%) | 1/3 (33.33%) | +33.33 pp |
| `Checkout/src/Cart.php::moveToWishlist()` | 0/44 (0.00%) | 20/44 (45.45%) | +45.45 pp |
| `Checkout/src/Cart.php::getErrors()` | 4/18 (22.22%) | 10/18 (55.56%) | +33.33 pp |
| `Checkout/src/Cart.php::getOrderAmount()` | 0/8 (0.00%) | 6/8 (75.00%) | +75.00 pp |
| `Checkout/src/Cart.php::haveMinimumOrderAmount()` | 2/3 (66.67%) | 3/3 (100.00%) | +33.33 pp |
| `Checkout/src/Cart.php::isItemsHaveSufficientQuantity()` | 4/6 (66.67%) | 4/6 (66.67%) | +0.00 pp |
| `Product/src/Type/AbstractType.php::getQtyRequest()` | 2/3 (66.67%) | 3/3 (100.00%) | +33.33 pp |
| `Product/src/Type/Simple.php::haveSufficientQuantity()` | 2/3 (66.67%) | 3/3 (100.00%) | +33.33 pp |
| `Product/src/Type/Simple.php::prepareForCart()` | 28/52 (53.85%) | 29/52 (55.77%) | +1.92 pp |
| `Shop/src/Http/Controllers/API/CartController.php::store()` | 14/26 (53.85%) | 24/26 (92.31%) | +38.46 pp |
| `Shop/src/Http/Controllers/API/CartController.php::destroySelected()` | 7/7 (100.00%) | 7/7 (100.00%) | +0.00 pp |
| `Shop/src/Http/Controllers/API/CartController.php::moveToWishlist()` | 0/8 (0.00%) | 8/8 (100.00%) | +100.00 pp |
| `Shop/src/Http/Controllers/API/CartController.php::update()` | 6/10 (60.00%) | 10/10 (100.00%) | +40.00 pp |
| `Shop/src/Http/Controllers/API/CartController.php::estimateShippingMethods()` | 25/26 (96.15%) | 25/26 (96.15%) | +0.00 pp |
| `Shop/src/Http/Controllers/API/CartController.php::storeCoupon()` | 0/31 (0.00%) | 10/31 (32.26%) | +32.26 pp |
| `Shop/src/Http/Controllers/API/CartController.php::destroyCoupon()` | 0/5 (0.00%) | 1/5 (20.00%) | +20.00 pp |
| `Shop/src/Http/Controllers/API/OnepageController.php::storeAddress()` | 17/29 (58.62%) | 21/29 (72.41%) | +13.79 pp |
| `Shop/src/Http/Controllers/API/OnepageController.php::storeShippingMethod()` | 8/11 (72.73%) | 11/11 (100.00%) | +27.27 pp |
| `Shop/src/Http/Controllers/API/OnepageController.php::storeOrder()` | 13/32 (40.62%) | 21/32 (65.62%) | +25.00 pp |
| `Shop/src/Http/Controllers/API/OnepageController.php::validateOrder()` | 13/21 (61.90%) | 16/21 (76.19%) | +14.29 pp |
