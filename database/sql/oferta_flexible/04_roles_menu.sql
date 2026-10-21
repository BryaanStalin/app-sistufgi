SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;
DECLARE @ahora datetime2(0) = CONVERT(datetime2(0), SYSDATETIMEOFFSET() AT TIME ZONE N'SA Pacific Standard Time');
INSERT INTO [dbo].[TB_ACCESO_ROL] ([titulo], [descripcion], [status], [created_at], [updated_at])
SELECT [r].[titulo], [r].[titulo], 1, @ahora, @ahora
FROM (VALUES (N'Administrador'), (N'Médico Triaje'), (N'Admisión')) AS [r] ([titulo])
WHERE NOT EXISTS (SELECT 1 FROM [dbo].[TB_ACCESO_ROL] WITH (UPDLOCK, HOLDLOCK) WHERE [titulo] = [r].[titulo]);
IF NOT EXISTS (SELECT 1 FROM [dbo].[TB_ACCESO_MENU] WITH (UPDLOCK, HOLDLOCK) WHERE [titulo] = N'OFERTA FLEXIBLE' AND [padre_id] IS NULL)
 INSERT INTO [dbo].[TB_ACCESO_MENU] ([titulo], [icono], [link], [orden], [status], [created_at], [updated_at])
 VALUES (N'OFERTA FLEXIBLE', N'bi bi-calendar-heart', N'#', 2, 1, @ahora, @ahora);
DECLARE @padre int = (SELECT TOP (1) [id] FROM [dbo].[TB_ACCESO_MENU] WHERE [titulo] = N'OFERTA FLEXIBLE' AND [padre_id] IS NULL ORDER BY [id]);
INSERT INTO [dbo].[TB_ACCESO_MENU] ([titulo], [icono], [link], [orden], [padre_id], [status], [created_at], [updated_at])
SELECT [m].[titulo], N'bi bi-calendar-check', [m].[link], [m].[orden], @padre, [m].[activo], @ahora, @ahora
FROM (VALUES
 (N'Gestión de Campañas', N'/oferta-flexible/campanias', 1, 1),
 (N'Gestión de Usuarios para Campañas', N'/oferta-flexible/usuarios-campanias', 2, 1),
 (N'Gestión de Servicios UPSS', N'/oferta-flexible/servicios', 3, 1),
 (N'Campaña de Salud de Especialidad', N'/oferta-flexible/triaje', 4, 0),
 (N'Campaña de Salud de Especialidad - Listado de pacientes', N'/oferta-flexible/admision', 5, 0)
) AS [m] ([titulo], [link], [orden], [activo])
WHERE NOT EXISTS (SELECT 1 FROM [dbo].[TB_ACCESO_MENU] WITH (UPDLOCK, HOLDLOCK) WHERE [link] = [m].[link]);
INSERT INTO [dbo].[TB_ACCESO_MENU_X_ROL] ([menu_principal_id], [rol_id], [status], [created_at], [updated_at])
SELECT [m].[id], [r].[id], 1, @ahora, @ahora
FROM [dbo].[TB_ACCESO_MENU] AS [m]
CROSS JOIN [dbo].[TB_ACCESO_ROL] AS [r]
WHERE (
 ([m].[id] = @padre AND [r].[titulo] IN (N'Administrador', N'Médico Triaje', N'Admisión')) OR
 ([m].[link] IN (N'/oferta-flexible/campanias', N'/oferta-flexible/usuarios-campanias', N'/oferta-flexible/servicios') AND [r].[titulo] = N'Administrador') OR
 ([m].[link] = N'/oferta-flexible/triaje' AND [r].[titulo] = N'Médico Triaje') OR
 ([m].[link] = N'/oferta-flexible/admision' AND [r].[titulo] = N'Admisión')
) AND NOT EXISTS (SELECT 1 FROM [dbo].[TB_ACCESO_MENU_X_ROL] WITH (UPDLOCK, HOLDLOCK) WHERE [menu_principal_id] = [m].[id] AND [rol_id] = [r].[id]);
-- Las dos pantallas clínicas se registran inactivas hasta implementar sus rutas.
-- No reactiva roles ni permisos deshabilitados deliberadamente por el administrador.
COMMIT TRANSACTION;
