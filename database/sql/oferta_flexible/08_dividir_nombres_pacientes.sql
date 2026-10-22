SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
SET XACT_ABORT ON;

IF OBJECT_ID(N'[dbo].[TB_PACIENTES_MOCK]', N'U') IS NULL
    THROW 51008, N'No existe TB_PACIENTES_MOCK.', 1;

BEGIN TRY
    BEGIN TRANSACTION;
    IF COL_LENGTH(N'dbo.TB_PACIENTES_MOCK', N'nombres') IS NULL
        ALTER TABLE [dbo].[TB_PACIENTES_MOCK] ADD [nombres] [varchar](150) NULL;
    IF COL_LENGTH(N'dbo.TB_PACIENTES_MOCK', N'apellidos') IS NULL
        ALTER TABLE [dbo].[TB_PACIENTES_MOCK] ADD [apellidos] [varchar](150) NULL;

    IF COL_LENGTH(N'dbo.TB_PACIENTES_MOCK', N'nombres_apellidos') IS NOT NULL
    BEGIN
        EXEC [sys].[sp_executesql] N'SELECT
    [id],
    [nombres_apellidos] AS [original],
    [nombres],
    [apellidos],
    CAST(LTRIM(RTRIM(REPLACE(REPLACE(REPLACE(REPLACE(
        COALESCE([nombres_apellidos], [nombres] + N'' '' + [apellidos], N''''),
        CHAR(9), N'' ''), CHAR(10), N'' ''), CHAR(13), N'' ''), NCHAR(160), N'' '')))
        AS [varchar](301)) AS [normalizado],
    CAST(NULL AS [varchar](301)) AS [nombres_nuevos],
    CAST(NULL AS [varchar](301)) AS [apellidos_nuevos],
    CAST(0 AS [int]) AS [palabras]
INTO [#OF_DIVISION_NOMBRES]
FROM [dbo].[TB_PACIENTES_MOCK];

WHILE EXISTS (SELECT 1 FROM [#OF_DIVISION_NOMBRES] WHERE [normalizado] LIKE N''%  %'')
BEGIN
    UPDATE [#OF_DIVISION_NOMBRES]
    SET [normalizado] = REPLACE([normalizado], N''  '', N'' '');
END;

UPDATE [p]
SET
    [nombres_nuevos] = CASE
        WHEN [s].[segundo] > 0 THEN LEFT([p].[normalizado], [s].[segundo] - 1)
        WHEN [s].[primero] > 0 THEN LEFT([p].[normalizado], [s].[primero] - 1)
        ELSE [p].[normalizado]
    END,
    [apellidos_nuevos] = CASE
        WHEN [s].[segundo] > 0 THEN SUBSTRING([p].[normalizado], [s].[segundo] + 1, 301)
        WHEN [s].[primero] > 0 THEN SUBSTRING([p].[normalizado], [s].[primero] + 1, 301)
        ELSE N''''
    END,
    [palabras] = CASE WHEN [p].[normalizado] = N'''' THEN 0
        ELSE LEN([p].[normalizado]) - LEN(REPLACE([p].[normalizado], N'' '', N'''')) + 1 END
FROM [#OF_DIVISION_NOMBRES] AS [p]
CROSS APPLY (SELECT CHARINDEX(N'' '', [p].[normalizado]) AS [primero]) AS [a]
CROSS APPLY (SELECT [a].[primero], CASE WHEN [a].[primero] > 0
    THEN CHARINDEX(N'' '', [p].[normalizado], [a].[primero] + 1) ELSE 0 END AS [segundo]) AS [s];

SELECT [id], [original], [normalizado],
    COALESCE([nombres], [nombres_nuevos]) AS [nombres_propuestos],
    COALESCE([apellidos], [apellidos_nuevos]) AS [apellidos_propuestos]
FROM [#OF_DIVISION_NOMBRES]
WHERE [nombres] IS NULL OR [apellidos] IS NULL
ORDER BY [id];

IF EXISTS (
    SELECT 1 FROM [#OF_DIVISION_NOMBRES]
    WHERE ([nombres] IS NULL AND DATALENGTH([nombres_nuevos]) > 150)
        OR ([apellidos] IS NULL AND DATALENGTH([apellidos_nuevos]) > 150)
)
BEGIN
    SELECT [id], [original], [nombres_nuevos], [apellidos_nuevos]
    FROM [#OF_DIVISION_NOMBRES]
    WHERE ([nombres] IS NULL AND DATALENGTH([nombres_nuevos]) > 150)
        OR ([apellidos] IS NULL AND DATALENGTH([apellidos_nuevos]) > 150);
    THROW 51008, N''Existen partes mayores de 150 caracteres. No se truncó ni actualizó ningún paciente.'', 1;
END;

UPDATE [destino]
SET [nombres] = COALESCE([destino].[nombres], [origen].[nombres_nuevos]),
    [apellidos] = COALESCE([destino].[apellidos], [origen].[apellidos_nuevos])
FROM [dbo].[TB_PACIENTES_MOCK] AS [destino]
INNER JOIN [#OF_DIVISION_NOMBRES] AS [origen] ON [origen].[id] = [destino].[id]
WHERE [destino].[nombres] IS NULL OR [destino].[apellidos] IS NULL;

SELECT [p].[id], [o].[original], [p].[nombres], [p].[apellidos], [o].[palabras]
FROM [dbo].[TB_PACIENTES_MOCK] AS [p]
INNER JOIN [#OF_DIVISION_NOMBRES] AS [o] ON [o].[id] = [p].[id]
WHERE [o].[palabras] < 4
ORDER BY [p].[id];
DROP TABLE [#OF_DIVISION_NOMBRES];';
    END;

    EXEC [sys].[sp_executesql] N'
        IF EXISTS (SELECT 1 FROM [dbo].[TB_PACIENTES_MOCK] WHERE [nombres] IS NULL OR [apellidos] IS NULL)
            THROW 51008, N''Hay datos pendientes. No se aplicó NOT NULL.'', 1;
        ALTER TABLE [dbo].[TB_PACIENTES_MOCK] ALTER COLUMN [nombres] [varchar](150) NOT NULL;
        ALTER TABLE [dbo].[TB_PACIENTES_MOCK] ALTER COLUMN [apellidos] [varchar](150) NOT NULL;
    ';
    IF COL_LENGTH(N'dbo.TB_PACIENTES_MOCK', N'nombres_apellidos') IS NOT NULL
        EXEC [sys].[sp_executesql] N'
            ALTER TABLE [dbo].[TB_PACIENTES_MOCK] ALTER COLUMN [nombres_apellidos] [varchar](200) NULL;
        ';
    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH;
