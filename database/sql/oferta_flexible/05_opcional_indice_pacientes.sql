-- Opcional: único cambio de estructura sobre una tabla existente expresamente solicitado.
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET NOCOUNT ON;
IF EXISTS (SELECT 1 FROM [dbo].[TB_PACIENTES_MOCK] WHERE [dni] IS NOT NULL GROUP BY [tipo_doc], [dni] HAVING COUNT_BIG(*) > 1)
    THROW 51002, N'Existen documentos duplicados. El índice no se creó y no se modificó ningún paciente.', 1;
IF NOT EXISTS (SELECT 1 FROM [sys].[indexes] WHERE [object_id] = OBJECT_ID(N'[dbo].[TB_PACIENTES_MOCK]') AND [name] = N'UQ_PACIENTES_MOCK_DOCUMENTO')
    CREATE UNIQUE INDEX [UQ_PACIENTES_MOCK_DOCUMENTO] ON [dbo].[TB_PACIENTES_MOCK] ([tipo_doc], [dni]) WHERE [dni] IS NOT NULL;
