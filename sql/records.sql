INSERT INTO roles (id, nombre, descripcion, jerarquia) VALUES
(1, 'super_administrador', 'Acceso total a la plataforma y gestion multi-restaurante.', 1),
(2, 'gerente_restaurante', 'Supervisión y administración de uno o varios restaurantes.', 2),
(3, 'gerente_general', 'Supervisión diaria y operativa del restaurante.', 3),
(4, 'jefe_de_sala', 'Gestión del servicio de comedor y atención al cliente.', 3),
(5, 'gerente_de_cocina', 'Supervisión de la cocina, inventario y menú.', 3),
(6, 'recursos_humanos', 'Gestión de nóminas y expedientes del personal.', 3),
(7, 'contador', 'Gestión de contabilidad, impuestos y reportes financieros.', 3),
(8, 'cajero', 'Manejo de la caja, cobros y cierres de turno.', 4),
(9, 'mesero', 'Toma y envío de pedidos, gestión de pagos en mesa.', 4),
(10, 'ayudante_camarero', 'Asistencia en el servicio de comedor (Runner).', 5),
(11, 'cocinero', 'Preparación de alimentos según KDS.', 5),
(12, 'mantenimiento', 'Limpieza y mantenimiento general.', 5);

-- Hash de ejemplo para 'administrador0023'. ¡REEMPLAZAR EN PRODUCCIÓN!
SET @password_hash = '$2y$10$41ZTdsasJyjfn2INH4LAHeibuP1mEENXbzUAjGRi9uT0QGWB0c4ai'; 

INSERT INTO usuarios (id, nombre_usuario, contrasena, nombre_completo, email, telefono) VALUES
-- 1. SUPER ADMINISTRADOR (Solo uno)
(101, 'admin_global', @password_hash, 'Global Admin', 'super@platform.com', '5511223344'),

-- 2. GERENTES DE RESTAURANTES (2 Registros)
(201, 'gter_rest_mex', @password_hash, 'Andrea Gomez', 'andrea@resm.com', '5511000001'),
(202, 'gter_rest_arg', @password_hash, 'Carlos Ruiz', 'carlos@resa.com', '5511000002'),

-- 3. GERENTES GENERALES (2 Registros)
(301, 'gter_gen_sushi', @password_hash, 'Laura Perez', 'laura@sushi.com', '5511000003'),
(302, 'gter_gen_tacos', @password_hash, 'Roberto Díaz', 'roberto@tacos.com', '5511000004'),

-- 4. OTROS ROLES DE JERARQUÍA 3 (Jefe de Sala, Gerente Cocina, RRHH, Contador)
(401, 'jefe_sala_sushi', @password_hash, 'María Soler', 'maria@sala.com', '5511000005'),
(402, 'jefe_sala_tacos', @password_hash, 'Pedro Lira', 'pedro@sala.com', '5511000006'),
(403, 'gter_cocina_sushi', @password_hash, 'Javier Soto', 'javier@chef.com', '5511000007'),
(404, 'gter_cocina_tacos', @password_hash, 'Elena Cruz', 'elena@chef.com', '5511000008'),
(405, 'rrhh_sushi', @password_hash, 'Felipe Marín', 'felipe@rrhh.com', '5511000009'),
(406, 'rrhh_tacos', @password_hash, 'Gloria Ramos', 'gloria@rrhh.com', '5511000010'),
(407, 'contador_sushi', @password_hash, 'Hugo Bernal', 'hugo@cont.com', '5511000011'),
(408, 'contador_tacos', @password_hash, 'Irene Castro', 'irene@cont.com', '5511000012'),

-- 5. CAJEROS y MESEROS (Jerarquía 4)
(501, 'cajero_sushi_a', @password_hash, 'Juan Ruiz', 'juan@caja.com', '5511000013'),
(502, 'cajero_tacos_b', @password_hash, 'Karla Mtz', 'karla@caja.com', '5511000014'),
(503, 'mesero_sushi_a', @password_hash, 'Luis Vzla', 'luis@mesero.com', '5511000015'),
(504, 'mesero_tacos_b', @password_hash, 'Marta Fdez', 'marta@mesero.com', '5511000016'),

-- 6. EMPLEADOS OPERATIVOS (Jerarquía 5)
(601, 'cocinero_sushi_a', @password_hash, 'Nico Mora', 'nico@op.com', '5511000017'),
(602, 'cocinero_tacos_b', @password_hash, 'Oscar Luna', 'oscar@op.com', '5511000018'),
(603, 'mante_sushi_a', @password_hash, 'Paco Sosa', 'paco@op.com', '5511000019'),
(604, 'ayud_tacos_b', @password_hash, 'Quime Rom', 'quime@op.com', '5511000020');

INSERT INTO restaurantes (id, nombre, direccion, gerenteres_id) VALUES
(1, 'Sushi Master', 'Avenida Principal 123, Ciudad de México', 201), -- Gerente: Andrea Gomez (ID 201)
(2, 'Tacos de Leyenda', 'Calle Larga 45, Guadalajara', 202);      -- Gerente: Carlos Ruiz (ID 202)

INSERT INTO usuarios_restaurantes (usuario_id, restaurante_id, rol_id, fecha_ingreso) VALUES
-- Asignación de Gerentes de Restaurantes (Multi-tenant)
(201, 1, 2, CURDATE()), -- Andrea (Gerente Res) en Sushi Master
(202, 2, 2, CURDATE()), -- Carlos (Gerente Res) en Tacos de Leyenda
(201, 2, 2, CURDATE()), -- Andrea (Gerente Res) en Tacos de Leyenda (Supervisa múltiples)

-- Equipo de Sushi Master (Restaurante ID 1)
(301, 1, 3, CURDATE()), -- Laura (Gte Gral) en Sushi
(401, 1, 4, CURDATE()), -- Maria (Jefe Sala) en Sushi
(403, 1, 5, CURDATE()), -- Javier (Gte Cocina) en Sushi
(405, 1, 6, CURDATE()), -- Felipe (RRHH) en Sushi
(407, 1, 7, CURDATE()), -- Hugo (Contador) en Sushi
(501, 1, 8, CURDATE()), -- Juan (Cajero) en Sushi
(503, 1, 9, CURDATE()), -- Luis (Mesero) en Sushi
(601, 1, 11, CURDATE()),-- Nico (Cocinero) en Sushi
(603, 1, 12, CURDATE()),-- Paco (Mantenimiento) en Sushi

-- Equipo de Tacos de Leyenda (Restaurante ID 2)
(302, 2, 3, CURDATE()), -- Roberto (Gte Gral) en Tacos
(402, 2, 4, CURDATE()), -- Pedro (Jefe Sala) en Tacos
(404, 2, 5, CURDATE()), -- Elena (Gte Cocina) en Tacos
(406, 2, 6, CURDATE()), -- Gloria (RRHH) en Tacos
(408, 2, 7, CURDATE()), -- Irene (Contador) en Tacos
(502, 2, 8, CURDATE()), -- Karla (Cajero) en Tacos
(504, 2, 9, CURDATE()), -- Marta (Mesero) en Tacos
(602, 2, 11, CURDATE()),-- Oscar (Cocinero) en Tacos
(604, 2, 10, CURDATE());-- Quime (Ayudante) en Tacos

INSERT INTO permisos (id, nombre, descripcion) VALUES
-- PERMISOS GLOBALES / SUPERVISIÓN
(1, 'gestion_plataforma', 'Crear/eliminar restaurantes y configurar la plataforma.'),
(2, 'gestion_gerentes', 'Crear/editar/eliminar Gerentes de Restaurantes (Jerarquía 2).'),
(3, 'ver_multi_restaurante', 'Capacidad de conmutar vistas entre diferentes restaurantes.'),

-- PERMISOS GERENCIALES / ADMINISTRACIÓN
(10, 'gestion_personal', 'Administrar roles, horarios, salarios y expedientes del personal.'),
(11, 'gestion_inventario_total', 'Acceso completo para ajustar y gestionar el inventario.'),
(12, 'gestion_menu', 'Modificar precios, recetas y disponibilidad del menú.'),
(13, 'anular_transacciones', 'Permiso para anular pedidos o realizar descuentos sin supervisor.'),
(14, 'ver_reportes_financieros', 'Acceso a Balance, P&G y reportes de alto nivel.'),

-- PERMISOS CONTABLES / RR.HH.
(20, 'gestion_nomina', 'Crear, editar y procesar la nómina.'),
(21, 'gestion_contabilidad', 'Registrar transacciones contables y preparar impuestos.'),
(22, 'ver_expedientes_rrhh', 'Acceso a expedientes personales (privado de RRHH).'),

-- PERMISOS OPERATIVOS / DIARIOS
(30, 'acceso_pos', 'Usar la interfaz de Punto de Venta (POS) para pedidos y cobros.'),
(31, 'enviar_pedidos_cocina', 'Enviar pedidos desde el POS o la mesa a la cocina (KDS).'),
(32, 'gestion_mesas_reservas', 'Asignar mesas y gestionar el sistema de reservas.'),
(33, 'ver_kds', 'Visualizar la pantalla de pedidos de la cocina (KDS).'),
(34, 'registro_basico_tareas', 'Registrar tareas de inventario, limpieza o mantenimiento.');

-- Super Administrador (ID 1): ACCESO TOTAL
INSERT INTO roles_permisos (rol_id, permiso_id) SELECT 1, id FROM permisos;

-- Gerente de Restaurante (ID 2): Supervisión y control gerencial amplio.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(2, 3), (2, 10), (2, 11), (2, 12), (2, 13), (2, 14), (2, 20), (2, 21), (2, 22);

-- Gerente General (ID 3): Gestión operativa diaria.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(3, 10), (3, 11), (3, 12), (3, 13), (3, 14), (3, 30), (3, 31), (3, 32);

-- Jefe de Sala (ID 4): Control de servicio y mesas.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(4, 32), (4, 31);

-- Gerente de Cocina (ID 5): Control de cocina e inventario.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(5, 11), (5, 12), (5, 33);

-- Recursos Humanos (ID 6): Personal y nómina.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(6, 20), (6, 22), (6, 10);

-- Contador (ID 7): Finanzas y fiscalidad.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(7, 14), (7, 21);

-- Cajero (ID 8): Solo POS y cobros.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(8, 30);

-- Mesero (ID 9): Pedidos y cobros limitados.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(9, 30), (9, 31);

-- Cocinero (ID 11): Solo visualización KDS.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(11, 33);

-- Mantenimiento (ID 12): Tareas básicas.
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES
(12, 34);

INSERT INTO registro_actividad (usuario_id, accion, detalles, ip_origen, user_agent) VALUES
(101, 'CREAR RESTAURANTE', 'Creación de Sushi Master (ID 1)', '203.0.113.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
(301, 'CIERRE DE CAJA', 'Cierre del turno de mañana con $1,500.00 en ventas.', '192.168.1.50', 'Chrome/100.0.0.0'),
(503, 'TOMA DE PEDIDO', 'Mesa 4: 3x Rollos de Sushi, 2x Bebidas.', '192.168.1.51', 'AppPOS/iOS');

INSERT INTO solicitudes_recuperacion (usuario_id, solicitado_por, token, creado_en) VALUES
(301, 201, 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', NOW()); -- Usuario 301 (Laura) solicita, Usuario 201 (Andrea) autoriza/genera.

INSERT INTO inventario (restaurante_id, nombre, unidad_medida, stock_actual, stock_minimo, costo_unitario) VALUES
(1, 'Arroz de Sushi', 'kg', 50.00, 10.00, 1.50),
(1, 'Filete de Salmón', 'unidad', 20.00, 5.00, 8.00),
(2, 'Tortillas de Maíz', 'unidad', 500.00, 100.00, 0.05);

INSERT INTO platos_menu (restaurante_id, nombre, precio_venta) VALUES 
(1, 'Sushi Roll Clásico', 12.50),
(1, 'Guisado de Res', 8.90);