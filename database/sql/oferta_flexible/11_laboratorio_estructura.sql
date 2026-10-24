SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
SET XACT_ABORT ON;
IF OBJECT_ID(N'[dbo].[TB_CAMPANIAS]', N'U') IS NULL
    THROW 51000, N'Ejecute primero los scripts base de Oferta Flexible.', 1;
GO
BEGIN TRANSACTION;
IF COL_LENGTH(N'dbo.TB_UPSS_CAMPAÑAS', N'HORA_LIMITE') IS NULL
    ALTER TABLE [dbo].[TB_UPSS_CAMPAÑAS] ADD [HORA_LIMITE] time(0) NULL;
IF COL_LENGTH(N'dbo.TB_UPSS_CAMPAÑAS', N'ENVIA_EESS') IS NULL
    ALTER TABLE [dbo].[TB_UPSS_CAMPAÑAS]
        ADD [ENVIA_EESS] bit NOT NULL CONSTRAINT [DF_UPSS_ENVIA_EESS] DEFAULT (1) WITH VALUES;
IF COL_LENGTH(N'dbo.TB_CAMPANIAS', N'incluye_laboratorio') IS NULL
    ALTER TABLE [dbo].[TB_CAMPANIAS]
        ADD [incluye_laboratorio] bit NOT NULL CONSTRAINT [DF_CAMPANIAS_LAB] DEFAULT (0) WITH VALUES;
IF COL_LENGTH(N'dbo.TB_CAMPANIAS', N'lab_ultimo_tm') IS NULL
    ALTER TABLE [dbo].[TB_CAMPANIAS]
        ADD [lab_ultimo_tm] int NOT NULL CONSTRAINT [DF_CAMPANIAS_TM] DEFAULT (0) WITH VALUES;
DECLARE @checks nvarchar(max) = N'';
SELECT @checks = @checks + N'ALTER TABLE [dbo].[TB_UPSS_CAMPAÑAS] DROP CONSTRAINT '
    + QUOTENAME([name]) + N';'
FROM [sys].[check_constraints]
WHERE [parent_object_id] = OBJECT_ID(N'[dbo].[TB_UPSS_CAMPAÑAS]')
    AND [definition] LIKE N'%TIPO_UPS%';
IF @checks <> N'' EXEC [sys].[sp_executesql] @checks;
ALTER TABLE [dbo].[TB_UPSS_CAMPAÑAS] WITH CHECK
    ADD CONSTRAINT [CK_UPSS_TIPO] CHECK ([TIPO_UPS] IN (N'P', N'E', N'L'));
IF OBJECT_ID(N'[dbo].[CK_CAMPANIA_USUARIOS_TIPO]', N'C') IS NOT NULL
    ALTER TABLE [dbo].[TB_CAMPANIA_USUARIOS] DROP CONSTRAINT [CK_CAMPANIA_USUARIOS_TIPO];
IF EXISTS (
    SELECT 1 FROM [sys].[columns]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_USUARIOS]')
        AND [name] = N'tipo' AND ([max_length] < 2 OR [system_type_id] <> 167)
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM [sys].[indexes]
        WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_USUARIOS]')
            AND [name] = N'IX_CAMPANIA_USUARIOS_USUARIO'
    )
        DROP INDEX [IX_CAMPANIA_USUARIOS_USUARIO] ON [dbo].[TB_CAMPANIA_USUARIOS];
    ALTER TABLE [dbo].[TB_CAMPANIA_USUARIOS] ALTER COLUMN [tipo] varchar(2) NOT NULL;
END;
IF OBJECT_ID(N'[dbo].[CK_CAMPANIA_USUARIOS_TIPO]', N'C') IS NULL
    ALTER TABLE [dbo].[TB_CAMPANIA_USUARIOS] WITH CHECK
        ADD CONSTRAINT [CK_CAMPANIA_USUARIOS_TIPO] CHECK ([tipo] IN (N'M', N'A', N'AL', N'L'));
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_USUARIOS]')
        AND [name] = N'IX_CAMPANIA_USUARIOS_USUARIO'
)
    CREATE INDEX [IX_CAMPANIA_USUARIOS_USUARIO]
        ON [dbo].[TB_CAMPANIA_USUARIOS] ([usuario_id], [tipo], [campania_id]) WHERE [deleted_at] IS NULL;
COMMIT TRANSACTION;
GO
BEGIN TRANSACTION;
IF OBJECT_ID(N'[dbo].[TB_CAMPANIA_ENLACES_LAB]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_CAMPANIA_ENLACES_LAB] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_CAMPANIA_ENLACES_LAB] PRIMARY KEY,
        [campania_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIAS]([id]),
        [admision_lab_usuario_id] int NOT NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [laboratorio_usuario_id] int NOT NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        CONSTRAINT [CK_ENLACES_LAB_DISTINTOS] CHECK ([admision_lab_usuario_id] <> [laboratorio_usuario_id]),
        [created_at] datetime2(0) NULL,
        [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id])
    );
END;
IF OBJECT_ID(N'[dbo].[TB_LAB_PACIENTES]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_LAB_PACIENTES] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_LAB_PACIENTES] PRIMARY KEY,
        [campania_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIAS]([id]),
        [paciente_id] int NOT NULL REFERENCES [dbo].[TB_PACIENTES_MOCK]([id]),
        [tm_numero] int NOT NULL,
        [eess_renipress] varchar(10) NULL,
        [eess_asignado_at] datetime2(0) NULL,
        [eess_asignado_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        CONSTRAINT [CK_LAB_PACIENTES_TM] CHECK ([tm_numero] > 0),
        [created_at] datetime2(0) NULL,
        [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id])
    );
END;
IF OBJECT_ID(N'[dbo].[TB_LAB_ATENCIONES]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_LAB_ATENCIONES] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_LAB_ATENCIONES] PRIMARY KEY,
        [lab_paciente_id] int NOT NULL REFERENCES [dbo].[TB_LAB_PACIENTES]([id]),
        [campania_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIAS]([id]),
        [fecha_atencion] datetime2(0) NOT NULL,
        [fua] varchar(20) NOT NULL,
        [ups_id_solicita] int NOT NULL REFERENCES [dbo].[TB_UPSS_CAMPAÑAS]([id]),
        [hoja_ruta_id] int NULL REFERENCES [dbo].[TB_HOJAS_RUTA]([id]),
        CONSTRAINT [CK_LAB_ATENCIONES_DIA] CHECK ([fecha_atencion] = CONVERT(date, [fecha_atencion])),
        [created_at] datetime2(0) NULL,
        [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id])
    );
END;
IF OBJECT_ID(N'[dbo].[TB_LAB_ATENCION_EXAMENES]', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[TB_LAB_ATENCION_EXAMENES] (
        [id] int IDENTITY(1,1) NOT NULL CONSTRAINT [PK_TB_LAB_ATENCION_EXAMENES] PRIMARY KEY,
        [atencion_id] int NOT NULL REFERENCES [dbo].[TB_LAB_ATENCIONES]([id]),
        [lab_paciente_id] int NOT NULL REFERENCES [dbo].[TB_LAB_PACIENTES]([id]),
        [campania_servicio_id] int NOT NULL REFERENCES [dbo].[TB_CAMPANIA_SERVICIOS]([id]),
        [ups_id] int NOT NULL REFERENCES [dbo].[TB_UPSS_CAMPAÑAS]([id]),
        [estado] char(1) NOT NULL CONSTRAINT [DF_LAB_EXAMEN_ESTADO] DEFAULT (N'P'),
        [tomado_at] datetime2(0) NULL,
        [tomado_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        CONSTRAINT [CK_LAB_EXAMEN_ESTADO] CHECK ([estado] IN (N'P', N'T', N'N')),
        [created_at] datetime2(0) NULL,
        [updated_at] datetime2(0) NULL,
        [created_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [updated_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id]),
        [deleted_at] datetime2(0) NULL,
        [deleted_by] int NULL REFERENCES [dbo].[TB_ACCESO_USUARIO]([id])
    );
END;
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_ENLACES_LAB]') AND [name] = N'UQ_ENLACES_LAB_USUARIO'
)
    CREATE UNIQUE INDEX [UQ_ENLACES_LAB_USUARIO]
        ON [dbo].[TB_CAMPANIA_ENLACES_LAB] ([campania_id], [laboratorio_usuario_id]) WHERE [deleted_at] IS NULL;
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_CAMPANIA_ENLACES_LAB]') AND [name] = N'IX_ENLACES_LAB_ADMISION'
)
    CREATE INDEX [IX_ENLACES_LAB_ADMISION]
        ON [dbo].[TB_CAMPANIA_ENLACES_LAB] ([campania_id], [admision_lab_usuario_id]) WHERE [deleted_at] IS NULL;
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_LAB_PACIENTES]') AND [name] = N'UQ_LAB_PACIENTE'
)
    CREATE UNIQUE INDEX [UQ_LAB_PACIENTE]
        ON [dbo].[TB_LAB_PACIENTES] ([campania_id], [paciente_id]) WHERE [deleted_at] IS NULL;
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_LAB_PACIENTES]') AND [name] = N'UQ_LAB_TM'
)
    CREATE UNIQUE INDEX [UQ_LAB_TM]
        ON [dbo].[TB_LAB_PACIENTES] ([campania_id], [tm_numero]) WHERE [deleted_at] IS NULL;
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_LAB_ATENCIONES]') AND [name] = N'UQ_LAB_ATENCION_DIA'
)
    CREATE UNIQUE INDEX [UQ_LAB_ATENCION_DIA]
        ON [dbo].[TB_LAB_ATENCIONES] ([lab_paciente_id], [fecha_atencion]) WHERE [deleted_at] IS NULL;
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_LAB_ATENCIONES]') AND [name] = N'IX_LAB_ATENCION_FECHA'
)
    CREATE INDEX [IX_LAB_ATENCION_FECHA]
        ON [dbo].[TB_LAB_ATENCIONES] ([campania_id], [fecha_atencion], [created_by]) WHERE [deleted_at] IS NULL;
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_LAB_ATENCION_EXAMENES]') AND [name] = N'UQ_LAB_EXAMEN_PACIENTE'
)
    CREATE UNIQUE INDEX [UQ_LAB_EXAMEN_PACIENTE]
        ON [dbo].[TB_LAB_ATENCION_EXAMENES] ([lab_paciente_id], [ups_id]) 
        WHERE [deleted_at] IS NULL AND [estado] <> CONVERT(char(1), N'N');
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_LAB_ATENCION_EXAMENES]') AND [name] = N'IX_LAB_EXAMEN_STOCK'
)
    CREATE INDEX [IX_LAB_EXAMEN_STOCK]
        ON [dbo].[TB_LAB_ATENCION_EXAMENES] ([campania_servicio_id], [estado], [atencion_id]) WHERE [deleted_at] IS NULL;
IF NOT EXISTS (
    SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_LAB_ATENCION_EXAMENES]') AND [name] = N'IX_LAB_EXAMEN_ATENCION'
)
    CREATE INDEX [IX_LAB_EXAMEN_ATENCION]
        ON [dbo].[TB_LAB_ATENCION_EXAMENES] ([atencion_id], [ups_id]);
COMMIT TRANSACTION;
GO
