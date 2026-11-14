/**
 * Bulk Order template.
 *
 * The base storefront module supplies the cart store, drawer, checkout and account behaviour
 * unchanged; this template only adds the order sheet, and the quick-order pad.
 * Alpine is started here, last, so those components are registered before it looks for them.
 */
import './order-tools.js';
import { startStorefront } from '../../../js/storefront.js';

startStorefront();
