SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET NOCOUNT ON;
SET XACT_ABORT ON;
DECLARE @menu int = (
    SELECT TOP (1) [id] FROM [dbo].[TB_ACCESO_MENU]
    WHERE [link] = N'/oferta-flexible/laboratorio/admision' ORDER BY [id]
);
IF @menu IS NULL OR NOT EXISTS (
    SELECT 1 FROM [dbo].[TB_ACCESO_ROL] WHERE [titulo] = N'Admisión Laboratorio'
)
    THROW 51004, N'Ejecute primero 12_laboratorio_roles_menu.sql.', 1;
DECLARE @ahora datetime2(0) = CONVERT(datetime2(0), SYSDATETIMEOFFSET() AT TIME ZONE N'SA Pacific Standard Time');
DECLARE @padre int = (SELECT [padre_id] FROM [dbo].[TB_ACCESO_MENU] WHERE [id] = @menu);
BEGIN TRANSACTION;
UPDATE [dbo].[TB_ACCESO_MENU]
SET [status] = 1, [deleted_by] = NULL, [updated_at] = @ahora
WHERE [id] IN (@menu, @padre);
INSERT INTO [dbo].[TB_ACCESO_MENU_X_ROL]
    ([menu_principal_id], [rol_id], [status], [created_at], [updated_at])
SELECT [m].[id], [r].[id], 1, @ahora, @ahora
FROM [dbo].[TB_ACCESO_MENU] AS [m]
CROSS JOIN [dbo].[TB_ACCESO_ROL] AS [r]
WHERE [m].[id] IN (@menu, @padre)
    AND [r].[titulo] IN (N'Admisión Laboratorio', N'Administrador')
    AND NOT EXISTS (
        SELECT 1 FROM [dbo].[TB_ACCESO_MENU_X_ROL] WITH (UPDLOCK, HOLDLOCK)
        WHERE [menu_principal_id] = [m].[id] AND [rol_id] = [r].[id]
    );
UPDATE [p]
SET [status] = 1, [deleted_by] = NULL, [updated_at] = @ahora
FROM [dbo].[TB_ACCESO_MENU_X_ROL] AS [p]
JOIN [dbo].[TB_ACCESO_ROL] AS [r] ON [r].[id] = [p].[rol_id]
WHERE [p].[menu_principal_id] IN (@menu, @padre)
    AND [r].[titulo] IN (N'Admisión Laboratorio', N'Administrador');
COMMIT TRANSACTION;
GO
