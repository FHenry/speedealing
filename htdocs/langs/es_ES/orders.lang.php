<?php
/* Copyright (C) 2012	Regis Houssin	<regis@dolibarr.fr>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$orders = array(
		'CHARSET' => 'UTF-8',
		'OrdersArea' => 'Área pedidos de clientes',
		'SuppliersOrdersArea' => 'Área pedidos a proveedores',
		'OrderCard' => 'Ficha pedido',
		'OrderId' => 'Id pedido',
		'Order' => 'Pedido',
		'Orders' => 'Pedidos',
		'OrderLine' => 'Línea de pedido',
		'OrderLines' => 'Order lines',
		'OrderFollow' => 'Seguimiento',
		'OrderDate' => 'Fecha pedido',
		'OrderToProcess' => 'Pedido a procesar',
		'NewOrder' => 'Nuevo pedido',
		'ToOrder' => 'Realizar pedido',
		'MakeOrder' => 'Realizar pedido',
		'SupplierOrder' => 'Pedido a proveedor',
		'SuppliersOrders' => 'Pedidos a proveedor',
		'SuppliersOrdersRunning' => 'Pedidos a proveedor en curso',
		'CustomerOrder' => 'Pedido de cliente',
		'CustomersOrders' => 'Pedidos de clientes',
		'CustomersOrdersRunning' => 'Pedidos de cliente en curso',
		'CustomersOrdersAndOrdersLines' => 'Pedidos de clientes y líneas de pedido',
		'OrdersToValid' => 'Pedidos de clientes a validar',
		'OrdersToBill' => 'Pedidos de clientes a facturar',
		'OrdersInProcess' => 'Pedidos de clientes en proceso',
		'OrdersToProcess' => 'Pedidos de clientes a procesar',
		'SuppliersOrdersToProcess' => 'Pedidos a proveedores a procesar',
		'StatusOrderCanceledShort' => 'Anulado',
		'StatusOrderDraftShort' => 'Borrador',
		'StatusOrderValidatedShort' => 'Validado',
		'StatusOrderSentShort' => 'Expedición en curso',
		'StatusOrderSent' => 'Envío en curso',
		'StatusOrderOnProcessShort' => 'Pdte. Recibir',
		'StatusOrderProcessedShort' => 'Procesado',
		'StatusOrderToBillShort' => 'A facturar',
		'StatusOrderToBill2Short' => 'To bill',
		'StatusOrderApprovedShort' => 'Aprobado',
		'StatusOrderRefusedShort' => 'Rechazado',
		'StatusOrderToProcessShort' => 'A procesar',
		'StatusOrderReceivedPartiallyShort' => 'Recibido parcialmente',
		'StatusOrderReceivedAllShort' => 'Recibido',
		'StatusOrderCanceled' => 'Anulado',
		'StatusOrderDraft' => 'Borrador (a validar)',
		'StatusOrderValidated' => 'Validado',
		'StatusOrderOnProcess' => 'Pendiente de recibir',
		'StatusOrderProcessed' => 'Procesado',
		'StatusOrderToBill' => 'A facturar',
		'StatusOrderToBill2' => 'To bill',
		'StatusOrderApproved' => 'Aprobado',
		'StatusOrderRefused' => 'Rechazado',
		'StatusOrderReceivedPartially' => 'Recibido parcialmente',
		'StatusOrderReceivedAll' => 'Recibido',
		'ShippingExist' => 'Existe una expedición',
		'DraftOrWaitingApproved' => 'Borrador o aprobado aún no controlado',
		'DraftOrWaitingShipped' => 'Borrador o validado aún no expedido',
		'MenuOrdersToBill' => 'Pedidos a facturar',
		'MenuOrdersToBill2' => 'Pedidos facturables',
		'SearchOrder' => 'Buscar un pedido',
		'Sending' => 'Envío',
		'Sendings' => 'Envíos',
		'ShipProduct' => 'Enviar producto',
		'Discount' => 'Descuento',
		'CreateOrder' => 'Crear pedido',
		'RefuseOrder' => 'Rechazar el pedido',
		'ApproveOrder' => 'Aceptar el pedido',
		'ValidateOrder' => 'Validar el pedido',
		'UnvalidateOrder' => 'Desvalidar el pedido',
		'DeleteOrder' => 'Eliminar el pedido',
		'CancelOrder' => 'Anular el pedido',
		'AddOrder' => 'Crear pedido',
		'AddToMyOrders' => 'Añadir a mis pedidos',
		'AddToOtherOrders' => 'Añadir a otros pedidos',
		'ShowOrder' => 'Mostrar pedido',
		'NoOpenedOrders' => 'Níngun pedido borrador',
		'NoOtherOpenedOrders' => 'Ningún otro pedido borrador',
		'OtherOrders' => 'Otros pedidos',
		'LastOrders' => 'Los %s últimos pedidos',
		'LastModifiedOrders' => 'Los %s últimos pedidos modificados',
		'LastClosedOrders' => 'Los %s últimos pedidos cerrados',
		'AllOrders' => 'Todos los pedidos',
		'NbOfOrders' => 'Número de pedidos',
		'OrdersStatistics' => 'Estadísticas de pedidos de clientes',
		'OrdersStatisticsSuppliers' => 'Estadísticas de pedidos a proveedores',
		'NumberOfOrdersByMonth' => 'Número de pedidos por mes',
		'AmountOfOrdersByMonthHT' => 'Importe total de pedidos por mes (sin IVA)',
		'ListOfOrders' => 'Listado de pedidos',
		'CloseOrder' => 'Cerrar pedido',
		'ConfirmCloseOrder' => '¿Está seguro de querer cerrar este pedido? Una vez cerrado, deberá facturarse',
		'ConfirmCloseOrderIfSending' => '¿Está seguro de querer cerrar este pedido? No debe cerrar un pedido  que aún no tiene sus productos enviados',
		'ConfirmDeleteOrder' => '¿Está seguro de querer eliminar este pedido?',
		'ConfirmValidateOrder' => '¿Está seguro de querer validar este pedido bajo la referencia <b>%s</b> ?',
		'ConfirmUnvalidateOrder' => '¿Está seguro de querer restaurar el pedido <b>%s</b> al estado borrador?',
		'ConfirmCancelOrder' => '¿Está seguro de querer anular este pedido?',
		'ConfirmMakeOrder' => '¿Está seguro de querer confirmar este pedido en fecha de <b>%s</b> ?',
		'GenerateBill' => 'Facturar',
		'ClassifyShipped' => 'Clasificar enviado',
		'ClassifyBilled' => 'Clasificar facturado',
		'ComptaCard' => 'Ficha contable',
		'DraftOrders' => 'Pedidos borrador',
		'RelatedOrders' => 'Pedidos adjuntos',
		'OnProcessOrders' => 'Pedidos en proceso',
		'RefOrder' => 'Ref. pedido',
		'RefCustomerOrder' => 'Ref. pedido cliente',
		'CustomerOrder' => 'Pedido de cliente',
		'RefCustomerOrderShort' => 'Ref. ped. cliente',
		'SendOrderByMail' => 'Enviar pedido por e-mail',
		'ActionsOnOrder' => 'Eventos sobre el pedido',
		'NoArticleOfTypeProduct' => 'No hay artículos de tipo \'producto\' y por lo tanto enviables en este pedido',
		'OrderMode' => 'Método de pedido',
		'AuthorRequest' => 'Autor/Solicitante',
		'UseCustomerContactAsOrderRecipientIfExist' => 'Utilizar dirección del contacto del cliente de seguimiento cliente si está definido en vez del tercero como destinatario de los pedidos',
		'RunningOrders' => 'Pedidos en curso',
		'UserWithApproveOrderGrant' => 'Usuarios habilitados para aprobar los pedidos',
		'PaymentOrderRef' => 'Pago pedido %s',
		'CloneOrder' => 'Clonar pedido',
		'ConfirmCloneOrder' => '¿Está seguro de querer clonar este pedido <b>%s</b>?',
		'DispatchSupplierOrder' => 'Recepción del pedido a proveedor %s',
		'DateDeliveryPlanned' => 'Date de livraison prévue',
		'SetDemandReason' => 'Définir l\'origine de la commande',
		////////// Types de contacts //////////
		'TypeContact_commande_internal_SALESREPFOLL' => 'Responsable seguimiento pedido cliente',
		'TypeContact_commande_internal_SHIPPING' => 'Responsable envío pedido cliente',
		'TypeContact_commande_external_BILLING' => 'Contacto cliente facturación pedido',
		'TypeContact_commande_external_SHIPPING' => 'Contacto cliente entrega pedido',
		'TypeContact_commande_external_CUSTOMER' => 'Contacto cliente seguimiento pedido',
		'TypeContact_order_supplier_internal_SALESREPFOLL' => 'Responsable seguimiento pedido a proveedor',
		'TypeContact_order_supplier_internal_SHIPPING' => 'Responsable recepción pedido a proveedor',
		'TypeContact_order_supplier_external_BILLING' => 'Contacto proveedor facturación pedido',
		'TypeContact_order_supplier_external_SHIPPING' => 'Contacto proveedor entrega pedido',
		'TypeContact_order_supplier_external_CUSTOMER' => 'Contacto proveedor seguimiento pedido',
		'Error_COMMANDE_SUPPLIER_ADDON_NotDefined' => 'Constante COMMANDE_SUPPLIER_ADDON no definida',
		'Error_COMMANDE_ADDON_NotDefined' => 'Constante COMMANDE_ADDON no definida',
		'Error_FailedToLoad_COMMANDE_SUPPLIER_ADDON_File' => 'Error en la carga del archivo módulo \'%s\'',
		'Error_FailedToLoad_COMMANDE_ADDON_File' => 'Error en la carga del archivo módulo \'%s\'',
		'Error_OrderNotChecked' => 'No se han seleccionado pedidos a facturar',
		// Sources
		'OrderSource0' => 'Presupuesto',
		'OrderSource1' => 'Internet',
		'OrderSource2' => 'Campaña por correo',
		'OrderSource3' => 'Campaña telefónica',
		'OrderSource4' => 'Campaña por fax',
		'OrderSource5' => 'Comercial',
		'OrderSource6' => 'Revistas',
		'QtyOrdered' => 'Cant. pedida',
		'AddDeliveryCostLine' => 'Añadir una línea de gastos de portes indicando el peso del pedido',
		// Documents models
		'PDFEinsteinDescription' => 'Modelo de pedido completo (logo...)',
		'PDFEdisonDescription' => 'Modelo de pedido simple',
		// Orders modes
		'OrderByMail' => 'Correo',
		'OrderByFax' => 'Fax',
		'OrderByEMail' => 'E-Mail',
		'OrderByWWW' => 'En línea',
		'OrderByPhone' => 'Teléfono',
		'CreateInvoiceForThisCustomer' => 'Facturar pedidos',
		'NoOrdersToInvoice' => 'Sin pedidos facturables',
		'CloseProcessedOrdersAutomatically' => 'Clasificar automáticamente como "Procesados" los pedidos seleccionados.',
		'MenuOrdersToBill2' => 'Pedidos facturables',
		'LinkedInvoices' => 'Linked invoices',
		'LinkedProposals' => 'Linked proposals'
);
?>