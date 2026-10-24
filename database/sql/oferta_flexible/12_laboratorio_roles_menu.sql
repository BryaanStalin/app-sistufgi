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
DECLARE @padre int = (
    SELECT TOP (1) [id] FROM [dbo].[TB_ACCESO_MENU]
    WHERE [titulo] = N'OFERTA FLEXIBLE' AND [padre_id] IS NULL ORDER BY [id]
);
IF @padre IS NULL
    THROW 51000, N'No existe el menú padre de Oferta Flexible.', 1;
INSERT INTO [dbo].[TB_ACCESO_ROL] ([titulo], [descripcion], [status], [created_at], [updated_at])
SELECT [r].[titulo], [r].[titulo], 1, @ahora, @ahora
FROM (VALUES (N'Admisión Laboratorio'), (N'Laboratorio')) AS [r] ([titulo])
WHERE NOT EXISTS (
    SELECT 1 FROM [dbo].[TB_ACCESO_ROL] WITH (UPDLOCK, HOLDLOCK) WHERE [titulo] = [r].[titulo]
);
INSERT INTO [dbo].[TB_ACCESO_MENU]
    ([titulo], [icono], [link], [orden], [padre_id], [status], [created_at], [updated_at])
SELECT [m].[titulo], N'bi bi-droplet', [m].[link], [m].[orden], @padre, 0, @ahora, @ahora
FROM (VALUES
    (N'Admisión Laboratorio', N'/oferta-flexible/laboratorio/admision', 6),
    (N'Toma de Muestra', N'/oferta-flexible/laboratorio/toma-muestra', 7),
    (N'Asignar Laboratorio', N'/oferta-flexible/laboratorio/asignar', 8)
) AS [m] ([titulo], [link], [orden])
WHERE NOT EXISTS (
    SELECT 1 FROM [dbo].[TB_ACCESO_MENU] WITH (UPDLOCK, HOLDLOCK) WHERE [link] = [m].[link]
);
INSERT INTO [dbo].[TB_ACCESO_MENU_X_ROL]
    ([menu_principal_id], [rol_id], [status], [created_at], [updated_at])
SELECT [m].[id], [r].[id], 1, @ahora, @ahora
FROM [dbo].[TB_ACCESO_MENU] AS [m]
CROSS JOIN [dbo].[TB_ACCESO_ROL] AS [r]
WHERE (
    ([m].[id] = @padre AND [r].[titulo] IN (N'Admisión Laboratorio', N'Laboratorio'))
    OR ([m].[link] = N'/oferta-flexible/laboratorio/admision'
        AND [r].[titulo] IN (N'Administrador', N'Admisión Laboratorio'))
    OR ([m].[link] IN (N'/oferta-flexible/laboratorio/toma-muestra', N'/oferta-flexible/laboratorio/asignar')
        AND [r].[titulo] IN (N'Administrador', N'Laboratorio'))
) AND NOT EXISTS (
    SELECT 1 FROM [dbo].[TB_ACCESO_MENU_X_ROL] WITH (UPDLOCK, HOLDLOCK)
    WHERE [menu_principal_id] = [m].[id] AND [rol_id] = [r].[id]
);
-- Las pantallas se activarán al implementar sus rutas en N, O y P.
-- No se reactivan permisos ni roles deshabilitados deliberadamente.
COMMIT TRANSACTION;
GO
