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
    THROW 51009, N'No existe TB_PACIENTES_MOCK.', 1;
IF COL_LENGTH(N'dbo.TB_PACIENTES_MOCK', N'nombres_apellidos') IS NULL
BEGIN
    PRINT N'La columna anterior ya fue eliminada.';
    RETURN;
END;
IF COL_LENGTH(N'dbo.TB_PACIENTES_MOCK', N'nombres') IS NULL
    OR COL_LENGTH(N'dbo.TB_PACIENTES_MOCK', N'apellidos') IS NULL
    THROW 51009, N'Ejecute primero el script 08 y valide el código actualizado.', 1;

DECLARE @tabla [int] = OBJECT_ID(N'[dbo].[TB_PACIENTES_MOCK]');
DECLARE @columna [int] = COLUMNPROPERTY(@tabla, N'nombres_apellidos', N'ColumnId');
DECLARE @dependencias TABLE ([tipo] [nvarchar](60), [nombre] [nvarchar](256));

INSERT INTO @dependencias ([tipo], [nombre])
SELECT N'Objeto SQL', OBJECT_SCHEMA_NAME([referencing_id]) + N'.' + OBJECT_NAME([referencing_id])
FROM [sys].[sql_expression_dependencies]
WHERE [referenced_id] = @tabla AND [referenced_minor_id] IN (0, @columna)
UNION ALL
SELECT N'Índice', [i].[name]
FROM [sys].[indexes] AS [i]
INNER JOIN [sys].[index_columns] AS [ic]
    ON [i].[object_id] = [ic].[object_id] AND [i].[index_id] = [ic].[index_id]
WHERE [ic].[object_id] = @tabla AND [ic].[column_id] = @columna
UNION ALL
SELECT N'Valor predeterminado', [name]
FROM [sys].[default_constraints]
WHERE [parent_object_id] = @tabla AND [parent_column_id] = @columna
UNION ALL
SELECT N'Restricción CHECK', [name]
FROM [sys].[check_constraints]
WHERE [parent_object_id] = @tabla AND [parent_column_id] = @columna
UNION ALL
SELECT N'Clave foránea', OBJECT_NAME([constraint_object_id])
FROM [sys].[foreign_key_columns]
WHERE ([parent_object_id] = @tabla AND [parent_column_id] = @columna)
    OR ([referenced_object_id] = @tabla AND [referenced_column_id] = @columna)
UNION ALL
SELECT N'Estadística manual', [s].[name]
FROM [sys].[stats] AS [s]
INNER JOIN [sys].[stats_columns] AS [sc]
    ON [s].[object_id] = [sc].[object_id] AND [s].[stats_id] = [sc].[stats_id]
WHERE [sc].[object_id] = @tabla AND [sc].[column_id] = @columna AND [s].[user_created] = 1;

IF EXISTS (SELECT 1 FROM @dependencias)
BEGIN
    SELECT [tipo], [nombre] FROM @dependencias ORDER BY [tipo], [nombre];
    THROW 51009, N'Hay dependencias. No se eliminó la columna.', 1;
END;

BEGIN TRY
    BEGIN TRANSACTION;
    EXEC [sys].[sp_executesql] N'
        IF EXISTS (SELECT 1 FROM [dbo].[TB_PACIENTES_MOCK] WHERE [nombres] IS NULL OR [apellidos] IS NULL)
            THROW 51009, N''Hay pacientes sin dividir. No se eliminó la columna.'', 1;
        ALTER TABLE [dbo].[TB_PACIENTES_MOCK] DROP COLUMN [nombres_apellidos];
    ';
    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH;
