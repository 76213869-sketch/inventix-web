# Checklist de Desarrollo - INVENTEX SaaS

## Fase 1: Setup & Infraestructura Tenant Core
- [x] Inicializar proyecto Laravel 12 con Inertia.js y Vue 3.
- [x] Configurar variables de entorno y soporte Docker (Dockerfile, docker-compose).
- [x] Crear migraciones de base de datos para todas las tablas:
  - [x] `planes` y `suscripciones`
  - [x] `empresas`
  - [x] `usuarios`
  - [x] `categorias` y `proveedores`
  - [x] `productos` y `recetas`
  - [x] `clientes`
  - [x] `compras` y `detalle_compras`
  - [x] `ventas` y `detalle_ventas`
  - [x] `movimientos_stock`
  - [x] `actividad_sistema`
- [x] Implementar Trait `BelongsToTenant` (Global Scope) para aislamiento de datos.
- [x] Implementar Trait `Blameable` (Audit creador/modificador).

## Fase 2: Autenticación, Roles y Tenant Middleware
- [x] Implementar autenticación básica (Login, Logout, password reset).
- [x] Configurar roles y permisos (RBAC) para Super Admin, Administrador, Cajero, Almacenero, Vendedor.
- [x] Crear Middleware de detección de Tenant (identificación de empresa activa).
- [x] Crear Middleware de verificación de Suscripción activa.

## Fase 3: Catálogos de Negocio (CRUDs)
- [ ] CRUD de Categorías.
- [ ] CRUD de Proveedores.
- [ ] CRUD de Clientes.
- [ ] CRUD de Productos (con variantes, JSON de metadatos, y recetas).

## Fase 4: Transacciones e Inventario (POS & Kardex)
- [ ] Módulo de Compras (Registro, Historial, aumento de stock).
- [ ] Módulo de Ventas / Punto de Venta (POS) responsivo con soporte para código de barras.
- [ ] Lógica de descuento de stock automático (con soporte para recetas e insumos).
- [ ] Kardex (consulta de movimientos de stock por producto, entradas, salidas y ajustes).

## Fase 5: Auditoría, Planes & Dashboard
- [ ] Observer de Auditoría Automática para registrar en `actividad_sistema`.
- [ ] Gestión SaaS de Planes y Suscripciones (Super Admin control).
- [ ] Dashboard operativo (ventas del día, compras, alertas de stock mínimo, bitácora reciente).

## Fase 6: Despliegue, PWA & Cierre
- [ ] Configurar Service Worker y Manifest para PWA-ready.
- [ ] Pruebas automatizadas de aislamiento y transacciones de stock.
- [ ] Preparación y pruebas del contenedor Docker para Railway.
