SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET NOCOUNT ON;
SET XACT_ABORT ON;
IF OBJECT_ID(N'[dbo].[TB_LAB_ATENCIONES]', N'U') IS NULL
    OR OBJECT_ID(N'[dbo].[TB_LAB_PACIENTES]', N'U') IS NULL
    OR OBJECT_ID(N'[dbo].[TB_HOJAS_RUTA]', N'U') IS NULL
    THROW 51007, N'No se encontraron las tablas de laboratorio y hojas de ruta.', 1;
DECLARE @ahora datetime2(0) = CONVERT(datetime2(0), SYSDATETIMEOFFSET() AT TIME ZONE N'SA Pacific Standard Time');
DECLARE @coincidencias TABLE (
    [atencion_id] int NOT NULL PRIMARY KEY,
    [hoja_id] int NULL,
    [cantidad] int NOT NULL
);
BEGIN TRANSACTION;
INSERT INTO @coincidencias ([atencion_id], [hoja_id], [cantidad])
SELECT [a].[id], MIN([h].[id]), COUNT([h].[id])
FROM [dbo].[TB_LAB_ATENCIONES] AS [a] WITH (UPDLOCK, HOLDLOCK)
INNER JOIN [dbo].[TB_LAB_PACIENTES] AS [p] ON [p].[id] = [a].[lab_paciente_id]
LEFT JOIN [dbo].[TB_HOJAS_RUTA] AS [h] WITH (UPDLOCK, HOLDLOCK)
    ON [h].[campania_id] = [a].[campania_id]
    AND [h].[paciente_id] = [p].[paciente_id]
    AND CONVERT(date, [h].[fecha_atencion]) = CONVERT(date, [a].[fecha_atencion])
    AND [h].[deleted_at] IS NULL
WHERE [a].[hoja_ruta_id] IS NULL
    AND [a].[deleted_at] IS NULL
    AND [p].[deleted_at] IS NULL
GROUP BY [a].[id];
SELECT [atencion_id], [hoja_id] AS [hoja_propuesta]
FROM @coincidencias WHERE [cantidad] = 1 ORDER BY [atencion_id];
UPDATE [a]
SET [hoja_ruta_id] = [c].[hoja_id], [updated_at] = @ahora
FROM [dbo].[TB_LAB_ATENCIONES] AS [a]
INNER JOIN @coincidencias AS [c] ON [c].[atencion_id] = [a].[id]
WHERE [c].[cantidad] = 1 AND [a].[hoja_ruta_id] IS NULL;
SELECT [a].[id] AS [atencion_id], [a].[campania_id], [a].[fecha_atencion], [c].[cantidad] AS [hojas_coincidentes]
FROM [dbo].[TB_LAB_ATENCIONES] AS [a]
INNER JOIN @coincidencias AS [c] ON [c].[atencion_id] = [a].[id]
WHERE [a].[hoja_ruta_id] IS NULL
ORDER BY [a].[id];
COMMIT TRANSACTION;
GO
