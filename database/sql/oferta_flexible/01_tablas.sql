-- Ejecutar manualmente en la base de la aplicación. No modifica tablas existentes.
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
SET XACT_ABORT ON;
IF OBJECT_ID(N'[dbo].[TB_ACCESO_USUARIO]', N'U') IS NULL OR
   OBJECT_ID(N'[dbo].[TB_UPSS_CAMPAÑAS]', N'U') IS NULL OR
   OBJECT_ID(N'[dbo].[TB_PACIENTES_MOCK]', N'U') IS NULL
    THROW 51000, N'Faltan las tablas existentes requeridas por Oferta Flexible.', 1;
BEGIN TRANSACTION;

IF OBJECT_ID(N'[dbo].[TB_CAMPANIAS]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_CAMPANIAS] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_CAMPANIAS] PRIMARY KEY,
        [created_at] datetime2(0) NULL, [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [nombre] varchar(200) NOT NULL,
        [fecha_inicio] datetime2(0) NOT NULL, [fecha_fin] datetime2(0) NOT NULL,
        [asociar_triaje_admision] bit NOT NULL, [mismos_servicios] bit NOT NULL,
        [cerrada] bit NOT NULL DEFAULT (0), [cerrada_at] datetime2(0) NULL,
        [cerrada_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        CONSTRAINT [CK_CAMPANIAS_FECHAS] CHECK ([fecha_fin] >= [fecha_inicio]),
        CONSTRAINT [CK_CAMPANIAS_DIAS] CHECK ([fecha_inicio] = CONVERT(date, [fecha_inicio]) AND [fecha_fin] = CONVERT(date, [fecha_fin]))
    );
END;

IF OBJECT_ID(N'[dbo].[TB_CAMPANIA_SERVICIOS]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_CAMPANIA_SERVICIOS] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_CAMPANIA_SERVICIOS] PRIMARY KEY,
        [created_at] datetime2(0) NULL, [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [campania_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIAS]([id]),
        [ups_id] int NOT NULL REFERENCES [dbo].[TB_UPSS_CAMPAÑAS]([id]),
        [fecha_servicio] datetime2(0) NOT NULL, [cupos] int NOT NULL,
        [cupo_base] int NULL, [cupo_personalizado] bit NOT NULL DEFAULT (0),
        CONSTRAINT [CK_CAMPANIA_SERVICIOS_CUPOS] CHECK ([cupos] > 0 AND ([cupo_base] IS NULL OR [cupo_base] > 0)),
        CONSTRAINT [CK_CAMPANIA_SERVICIOS_DIA] CHECK ([fecha_servicio] = CONVERT(date, [fecha_servicio]))
    );
END;

IF OBJECT_ID(N'[dbo].[TB_CAMPANIA_USUARIOS]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_CAMPANIA_USUARIOS] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_CAMPANIA_USUARIOS] PRIMARY KEY,
        [created_at] datetime2(0) NULL, [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [campania_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIAS]([id]),
        [usuario_id] int NOT NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [tipo] char(1) NOT NULL,
        CONSTRAINT [CK_CAMPANIA_USUARIOS_TIPO] CHECK ([tipo] IN (N'M', N'A'))
    );
END;

IF OBJECT_ID(N'[dbo].[TB_CAMPANIA_ENLACES]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_CAMPANIA_ENLACES] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_CAMPANIA_ENLACES] PRIMARY KEY,
        [created_at] datetime2(0) NULL, [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [campania_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIAS]([id]),
        [medico_usuario_id] int NOT NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [admision_usuario_id] int NOT NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        CONSTRAINT [CK_CAMPANIA_ENLACES_DISTINTOS] CHECK ([medico_usuario_id] <> [admision_usuario_id])
    );
END;

IF OBJECT_ID(N'[dbo].[TB_HOJAS_RUTA]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_HOJAS_RUTA] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_HOJAS_RUTA] PRIMARY KEY,
        [created_at] datetime2(0) NULL, [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [campania_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIAS]([id]),
        [paciente_id] int NOT NULL REFERENCES [dbo].[TB_PACIENTES_MOCK]([id]),
        [fecha_atencion] datetime2(0) NOT NULL,
        [medico_usuario_id] int NOT NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        CONSTRAINT [CK_HOJAS_RUTA_DIA] CHECK ([fecha_atencion] = CONVERT(date, [fecha_atencion]))
    );
END;

IF OBJECT_ID(N'[dbo].[TB_HOJA_RUTA_DETALLE]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_HOJA_RUTA_DETALLE] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_HOJA_RUTA_DETALLE] PRIMARY KEY,
        [created_at] datetime2(0) NULL, [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [hoja_ruta_id] int NOT NULL REFERENCES [dbo].[TB_HOJAS_RUTA]([id]),
        [campania_servicio_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIA_SERVICIOS]([id])
    );
END;

IF OBJECT_ID(N'[dbo].[TB_HOJA_RUTA_IMPRESIONES]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_HOJA_RUTA_IMPRESIONES] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_HOJA_RUTA_IMPRESIONES] PRIMARY KEY,
        [created_at] datetime2(0) NULL, [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [hoja_ruta_id] int NOT NULL REFERENCES [dbo].[TB_HOJAS_RUTA]([id]),
        [impreso_por] int NOT NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [impreso_at] datetime2(0) NOT NULL
    );
END;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIAS]') AND [name] = N'IX_CAMPANIAS_FECHAS')
    CREATE INDEX [IX_CAMPANIAS_FECHAS] ON [dbo].[TB_CAMPANIAS] ([cerrada], [fecha_inicio], [fecha_fin]);

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_SERVICIOS]') AND [name] = N'UQ_CAMPANIA_SERVICIOS')
    CREATE UNIQUE INDEX [UQ_CAMPANIA_SERVICIOS] ON [dbo].[TB_CAMPANIA_SERVICIOS] ([campania_id], [ups_id], [fecha_servicio]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_SERVICIOS]') AND [name] = N'IX_CAMPANIA_SERVICIOS_UPS')
    CREATE INDEX [IX_CAMPANIA_SERVICIOS_UPS] ON [dbo].[TB_CAMPANIA_SERVICIOS] ([ups_id], [campania_id]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_USUARIOS]') AND [name] = N'UQ_CAMPANIA_USUARIOS')
    CREATE UNIQUE INDEX [UQ_CAMPANIA_USUARIOS] ON [dbo].[TB_CAMPANIA_USUARIOS] ([campania_id], [usuario_id]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_USUARIOS]') AND [name] = N'IX_CAMPANIA_USUARIOS_USUARIO')
    CREATE INDEX [IX_CAMPANIA_USUARIOS_USUARIO] ON [dbo].[TB_CAMPANIA_USUARIOS] ([usuario_id], [tipo], [campania_id]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_ENLACES]') AND [name] = N'UQ_CAMPANIA_ENLACES_MEDICO')
    CREATE UNIQUE INDEX [UQ_CAMPANIA_ENLACES_MEDICO] ON [dbo].[TB_CAMPANIA_ENLACES] ([campania_id], [medico_usuario_id]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_ENLACES]') AND [name] = N'IX_CAMPANIA_ENLACES_ADMISION')
    CREATE INDEX [IX_CAMPANIA_ENLACES_ADMISION] ON [dbo].[TB_CAMPANIA_ENLACES] ([admision_usuario_id], [campania_id]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_HOJAS_RUTA]') AND [name] = N'UQ_HOJAS_RUTA_PACIENTE_DIA')
    CREATE UNIQUE INDEX [UQ_HOJAS_RUTA_PACIENTE_DIA] ON [dbo].[TB_HOJAS_RUTA] ([campania_id], [paciente_id], [fecha_atencion]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_HOJAS_RUTA]') AND [name] = N'IX_HOJAS_RUTA_MEDICO')
    CREATE INDEX [IX_HOJAS_RUTA_MEDICO] ON [dbo].[TB_HOJAS_RUTA] ([campania_id], [medico_usuario_id], [fecha_atencion]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_HOJA_RUTA_DETALLE]') AND [name] = N'UQ_HOJA_RUTA_DETALLE')
    CREATE UNIQUE INDEX [UQ_HOJA_RUTA_DETALLE] ON [dbo].[TB_HOJA_RUTA_DETALLE] ([hoja_ruta_id], [campania_servicio_id]) WHERE [deleted_at] IS NULL;

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_HOJA_RUTA_DETALLE]') AND [name] = N'IX_HOJA_RUTA_DETALLE_SERVICIO')
    CREATE INDEX [IX_HOJA_RUTA_DETALLE_SERVICIO] ON [dbo].[TB_HOJA_RUTA_DETALLE] ([campania_servicio_id], [hoja_ruta_id]);

IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_HOJA_RUTA_IMPRESIONES]') AND [name] = N'IX_HOJA_RUTA_IMPRESIONES_HOJA')
    CREATE INDEX [IX_HOJA_RUTA_IMPRESIONES_HOJA] ON [dbo].[TB_HOJA_RUTA_IMPRESIONES] ([hoja_ruta_id], [impreso_at]);

COMMIT TRANSACTION;
GO
IF OBJECT_ID(N'[dbo].[TR_HOJA_RUTA_IMPRESIONES_INMUTABLE]', N'TR') IS NULL
    EXEC(N'CREATE TRIGGER [dbo].[TR_HOJA_RUTA_IMPRESIONES_INMUTABLE] ON [dbo].[TB_HOJA_RUTA_IMPRESIONES] INSTEAD OF UPDATE, DELETE AS BEGIN SET NOCOUNT ON; THROW 51001, N''Las impresiones solo admiten inserciones.'', 1; END');
GO
