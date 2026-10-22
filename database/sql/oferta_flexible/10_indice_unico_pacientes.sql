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
    THROW 51010, N'No existe TB_PACIENTES_MOCK.', 1;

IF EXISTS (
    SELECT 1 FROM [sys].[indexes] AS [i]
    WHERE [i].[object_id] = OBJECT_ID(N'[dbo].[TB_PACIENTES_MOCK]')
        AND [i].[is_unique] = 1 AND [i].[is_disabled] = 0
        AND [i].[is_hypothetical] = 0 AND [i].[has_filter] = 1
        AND UPPER(REPLACE(REPLACE(REPLACE([i].[filter_definition], N' ', N''), N'(', N''), N')', N''))
            = N'[DNI]ISNOTNULL'
        AND (SELECT COUNT(*) FROM [sys].[index_columns] AS [ic]
            WHERE [ic].[object_id] = [i].[object_id] AND [ic].[index_id] = [i].[index_id]
                AND [ic].[key_ordinal] > 0) = 2
        AND EXISTS (SELECT 1 FROM [sys].[index_columns] AS [ic]
            INNER JOIN [sys].[columns] AS [c]
                ON [c].[object_id] = [ic].[object_id] AND [c].[column_id] = [ic].[column_id]
            WHERE [ic].[object_id] = [i].[object_id] AND [ic].[index_id] = [i].[index_id]
                AND [ic].[key_ordinal] = 1 AND [c].[name] = N'tipo_doc')
        AND EXISTS (SELECT 1 FROM [sys].[index_columns] AS [ic]
            INNER JOIN [sys].[columns] AS [c]
                ON [c].[object_id] = [ic].[object_id] AND [c].[column_id] = [ic].[column_id]
            WHERE [ic].[object_id] = [i].[object_id] AND [ic].[index_id] = [i].[index_id]
                AND [ic].[key_ordinal] = 2 AND [c].[name] = N'dni')
)
BEGIN
    PRINT N'Ya existe el índice único filtrado de documentos.';
    RETURN;
END;

IF EXISTS (
    SELECT 1 FROM [dbo].[TB_PACIENTES_MOCK]
    WHERE [dni] IS NOT NULL GROUP BY [tipo_doc], [dni] HAVING COUNT_BIG(*) > 1
)
BEGIN
    SELECT [tipo_doc], [dni], COUNT_BIG(*) AS [cantidad]
    FROM [dbo].[TB_PACIENTES_MOCK]
    WHERE [dni] IS NOT NULL GROUP BY [tipo_doc], [dni] HAVING COUNT_BIG(*) > 1;
    PRINT N'Hay documentos duplicados. No se creó el índice ni se modificaron pacientes.';
    RETURN;
END;

IF EXISTS (SELECT 1 FROM [sys].[indexes]
    WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_PACIENTES_MOCK]') AND [name] = N'UQ_PACIENTES_MOCK_DOCUMENTO')
    THROW 51010, N'Existe un índice con ese nombre y otra definición. Revíselo antes de continuar.', 1;

CREATE UNIQUE INDEX [UQ_PACIENTES_MOCK_DOCUMENTO]
    ON [dbo].[TB_PACIENTES_MOCK] ([tipo_doc], [dni]) WHERE [dni] IS NOT NULL;
