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
DECLARE @examenes TABLE (
    [codigo] int, [nombre] nvarchar(255), [hora] time(0), [envia] bit
);
INSERT INTO @examenes ([codigo], [nombre], [hora], [envia]) VALUES
    (9001, N'Hemograma Completo', NULL, 1),
    (9002, N'Glucosa en Sangre', NULL, 1),
    (9003, N'Urea', NULL, 1),
    (9004, N'Creatinina', NULL, 1),
    (9005, N'Perfil Lipídico', N'10:30:00', 1),
    (9006, N'Perfil Hepático', N'10:30:00', 1),
    (9007, N'Orina Tira Reactiva', NULL, 0);
IF EXISTS (
    SELECT 1 FROM [dbo].[TB_UPSS_CAMPAÑAS] AS [u] WITH (UPDLOCK, HOLDLOCK)
    JOIN @examenes AS [e] ON [e].[codigo] = [u].[ID_UPS]
    WHERE [u].[ACTIVO] = 1
        AND ([u].[TIPO_UPS] <> N'L' OR [u].[DESCRIPCION_UPS] <> [e].[nombre])
)
    THROW 51000, N'Los códigos 9001 a 9007 están ocupados por otro servicio activo. Revise el catálogo.', 1;
INSERT INTO [dbo].[TB_UPSS_CAMPAÑAS]
    ([ID_UPS], [DESCRIPCION_UPS], [TIPO_UPS], [ACTIVO], [HORA_LIMITE], [ENVIA_EESS],
     [created_at], [updated_at])
SELECT [e].[codigo], [e].[nombre], N'L', 1, [e].[hora], [e].[envia], @ahora, @ahora
FROM @examenes AS [e]
WHERE NOT EXISTS (
    SELECT 1 FROM [dbo].[TB_UPSS_CAMPAÑAS] WITH (UPDLOCK, HOLDLOCK)
    WHERE [TIPO_UPS] = N'L' AND [DESCRIPCION_UPS] = [e].[nombre]
);
COMMIT TRANSACTION;
GO
