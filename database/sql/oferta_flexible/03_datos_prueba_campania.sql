-- Opcional. Fechas fijas para que repetir el script no agregue otra campaña.
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
IF NOT EXISTS (SELECT 1 FROM [dbo].[TB_CAMPANIAS] WITH (UPDLOCK, HOLDLOCK) WHERE [nombre] = N'Campaña de ejemplo Oferta Flexible' AND [fecha_inicio] = CONVERT(datetime2(0), N'20261101', 112))
 INSERT INTO [dbo].[TB_CAMPANIAS] ([nombre], [fecha_inicio], [fecha_fin], [asociar_triaje_admision], [mismos_servicios], [created_at], [updated_at])
 VALUES (N'Campaña de ejemplo Oferta Flexible', CONVERT(datetime2(0), N'20261101', 112), CONVERT(datetime2(0), N'20261102', 112), 1, 1, CONVERT(datetime2(0), SYSDATETIMEOFFSET() AT TIME ZONE N'SA Pacific Standard Time'), CONVERT(datetime2(0), SYSDATETIMEOFFSET() AT TIME ZONE N'SA Pacific Standard Time'));
DECLARE @campania int = (SELECT TOP (1) [id] FROM [dbo].[TB_CAMPANIAS] WHERE [nombre] = N'Campaña de ejemplo Oferta Flexible' AND [fecha_inicio] = CONVERT(datetime2(0), N'20261101', 112) ORDER BY [id]);
INSERT INTO [dbo].[TB_CAMPANIA_SERVICIOS] ([campania_id], [ups_id], [fecha_servicio], [cupos], [cupo_base], [cupo_personalizado], [created_at], [updated_at])
SELECT @campania, [s].[id], CONVERT(datetime2(0), [d].[dia], 112), 20, 20, 0,
 CONVERT(datetime2(0), SYSDATETIMEOFFSET() AT TIME ZONE N'SA Pacific Standard Time'), CONVERT(datetime2(0), SYSDATETIMEOFFSET() AT TIME ZONE N'SA Pacific Standard Time')
FROM (SELECT TOP (4) [id] FROM [dbo].[TB_UPSS_CAMPAÑAS] WHERE [ACTIVO] = 1 ORDER BY [id]) AS [s]
CROSS JOIN (VALUES (N'20261101'), (N'20261102')) AS [d] ([dia])
WHERE NOT EXISTS (SELECT 1 FROM [dbo].[TB_CAMPANIA_SERVICIOS] WITH (UPDLOCK, HOLDLOCK) WHERE [campania_id] = @campania AND [ups_id] = [s].[id] AND [fecha_servicio] = CONVERT(datetime2(0), [d].[dia], 112));
-- Asignar desde el módulo después de verificar los usuarios y sus roles activos.
-- INSERT INTO [dbo].[TB_CAMPANIA_USUARIOS] ([campania_id], [usuario_id], [tipo]) VALUES (@campania, <id_medico>, N'M');
-- INSERT INTO [dbo].[TB_CAMPANIA_USUARIOS] ([campania_id], [usuario_id], [tipo]) VALUES (@campania, <id_admision>, N'A');
COMMIT TRANSACTION;
