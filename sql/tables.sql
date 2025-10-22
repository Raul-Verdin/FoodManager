-- Usuarios base
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100),
    email VARCHAR(100),
    telefono VARCHAR(20),
    token_recuperacion VARCHAR(255),
    token_expira DATETIME,
    verificado BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE
);

-- Roles del sistema
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    jerarquia INT NOT NULL
);

-- Permisos
CREATE TABLE permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT
);

-- Relación roles y permisos
CREATE TABLE roles_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
    UNIQUE (rol_id, permiso_id)
);

-- Restaurantes
CREATE TABLE restaurantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion TEXT,
    gerenteres_id INT,
    FOREIGN KEY (gerenteres_id) REFERENCES usuarios(id)
);

-- Relación usuario - restaurante - rol
CREATE TABLE usuarios_restaurantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    restaurante_id INT NOT NULL,
    rol_id INT NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_ingreso DATE,
    fecha_salida DATE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE (usuario_id, restaurante_id)
);

-- Registro de Actividad
CREATE TABLE registro_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(255),
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    detalles TEXT,
    ip_origen VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Solicitudes de recuperación
CREATE TABLE solicitudes_recuperacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    solicitado_por INT,
    token VARCHAR(255),
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    usado BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (solicitado_por) REFERENCES usuarios(id)
);

-- *************************************************************

-- TABLA REQUERIDA: INVENTARIO
CREATE TABLE inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurante_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    unidad_medida VARCHAR(20) NOT NULL, -- Ej: kg, litro, unidad
    stock_actual DECIMAL(10, 2) DEFAULT 0.00,
    stock_minimo DECIMAL(10, 2) DEFAULT 0.00,
    costo_unitario DECIMAL(10, 2) DEFAULT 0.00,
    fecha_ultimo_ajuste DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
    UNIQUE (restaurante_id, nombre)
);

-- TABLA PLATOS_MENU (El producto final que se vende)
CREATE TABLE platos_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurante_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    precio_venta DECIMAL(10, 2) NOT NULL,
    costo_produccion DECIMAL(10, 2) DEFAULT 0.00, -- Se calcula dinámicamente
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
    UNIQUE (restaurante_id, nombre)
);

-- TABLA RECETAS (Vincula los platos con los ingredientes del inventario)
CREATE TABLE recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plato_id INT NOT NULL,
    inventario_id INT NOT NULL,
    cantidad_requerida DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (plato_id) REFERENCES platos_menu(id) ON DELETE CASCADE,
    -- Debe hacer referencia al inventario del restaurante, por eso usamos la tabla inventario
    FOREIGN KEY (inventario_id) REFERENCES inventario(id) ON DELETE RESTRICT,
    UNIQUE (plato_id, inventario_id)
);

-- TABLA REQUERIDA: NOMINA
CREATE TABLE nomina (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurante_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_pago DATE NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fin DATE NOT NULL,
    horas_trabajadas DECIMAL(10, 2) DEFAULT 0.00,
    salario_base DECIMAL(10, 2) NOT NULL,
    deducciones DECIMAL(10, 2) DEFAULT 0.00,
    bonificaciones DECIMAL(10, 2) DEFAULT 0.00,
    total_pagado DECIMAL(10, 2) NOT NULL,
    registrado_por_id INT NOT NULL,
    FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (registrado_por_id) REFERENCES usuarios(id) ON DELETE RESTRICT
);

-- *************************************************************
-- Tablas necesarias para POS 
-- *************************************************************

-- TABLA ORDENES (El ticket o la transacción general)
CREATE TABLE ordenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurante_id INT NOT NULL,
    usuario_id INT NOT NULL,  -- Mesero o cajero que tomó la orden
    mesa_id INT DEFAULT NULL, -- Si usamos mesas, se puede vincular aquí
    fecha_orden DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE', -- Ej: PENDIENTE, EN_PROCESO, COMPLETADA, PAGADA, CANCELADA
    total_neto DECIMAL(10, 2) DEFAULT 0.00,
    impuestos DECIMAL(10, 2) DEFAULT 0.00,
    total_bruto DECIMAL(10, 2) DEFAULT 0.00,
    metodo_pago VARCHAR(50) DEFAULT NULL, -- Ej: Efectivo, Tarjeta, Crédito
    FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
);

-- TABLA DETALLE_ORDENES (Los ítems individuales vendidos)
CREATE TABLE detalle_ordenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    plato_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10, 2) NOT NULL, -- Precio de venta al momento de la orden
    costo_unitario DECIMAL(10, 2) NOT NULL,  -- Costo de producción al momento de la orden
    notas TEXT, -- Ej: "Sin cebolla", "Bien cocido"
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
    FOREIGN KEY (plato_id) REFERENCES platos_menu(id) ON DELETE RESTRICT
);

-- TABLA MESAS (Necesaria para el siguiente paso, pero útil aquí para el POS)
CREATE TABLE mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurante_id INT NOT NULL,
    numero VARCHAR(10) NOT NULL,
    capacidad INT NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'LIBRE', -- Ej: LIBRE, OCUPADA, RESERVADA
    FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id) ON DELETE CASCADE,
    UNIQUE (restaurante_id, numero)
);

-- 2. Crear tabla RESERVAS
CREATE TABLE IF NOT EXISTS reservas (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    restaurante_id INT(11) NOT NULL,
    nombre_cliente VARCHAR(100) NOT NULL,
    telefono_cliente VARCHAR(20),
    email_cliente VARCHAR(100),
    fecha_reserva DATE NOT NULL,
    hora_reserva TIME NOT NULL,
    numero_personas INT(11) NOT NULL,
    mesa_id INT(11) NULL, -- Mesa asignada (opcional, se asigna al sentar)
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE', -- PENDIENTE, CONFIRMADA, SENTADA, CANCELADA, EXPIRADA
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurante_id) REFERENCES restaurantes(id),
    FOREIGN KEY (mesa_id) REFERENCES mesas(id)
);
