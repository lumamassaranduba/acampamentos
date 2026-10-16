-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 16/10/2026 às 20:39
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `gestao_acampamentos`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `apostilas_musicas`
--

CREATE TABLE `apostilas_musicas` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) NOT NULL,
  `artista` varchar(255) DEFAULT NULL,
  `categoria` enum('TEMA','MISSA','MOMENTOS') NOT NULL,
  `subcategoria` varchar(100) DEFAULT 'Geral',
  `letra` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `apostilas_musicas`
--

INSERT INTO `apostilas_musicas` (`id`, `edicao_id`, `titulo`, `artista`, `categoria`, `subcategoria`, `letra`, `created_at`, `updated_at`) VALUES
(1, 2, 'Me Leva', 'Colo de Deus', 'TEMA', 'Tema', 'Lança fogo em mim, Espírito de Deus\r\nFogo abrasador\r\nPois sedento estou\r\nVenha, não demores\r\nVenha me buscar\r\nPois sedento estou\r\nDo teu fogo abrasador\r\n\r\nE esse fogo não se apagará jamais\r\nE esse fogo não se apagará\r\nE esse fogo não se apagará jamais\r\nE esse fogo não se apagará\r\n\r\nMe leva a esse lugar\r\nMe leva a esse lugar\r\nOnde o fogo não se apaga\r\nOnde o fogo não se apaga\r\nOnde o fogo não se apaga, e não se apagará jamais', '2026-09-30 13:22:41', '2026-09-30 13:22:41'),
(2, 2, 'Festa do Fogo', 'Frei Gilson', 'TEMA', 'Tema de animação', 'Hoje é a festa do fogo\r\nO novo pentecostes vai acontecer\r\nUnidos esperamos sua glória\r\nA virgem Maria vai interceder\r\nE vai acontecer, o novo pentecostes (3x)\r\n\r\nFogo fogo fogo fogo fogo fogo fogo do céu (4x)\r\n\r\nPosso dançar no teu espírito (posso dançar)\r\nPosso pular com teu poder (posso pular)\r\nPosso correr na tua unção\r\nPosso gritar a tua glória\r\n\r\nFogo fogo fogo fogo fogo fogo fogo do céu (4x)', '2026-09-30 13:24:48', '2026-09-30 13:24:48'),
(4, 2, 'Festa do Fogo', 'Frei Gilson', 'MISSA', 'Entrada', 'Hoje é a festa do fogo\r\nO novo pentecostes vai acontecer\r\nUnidos esperamos sua glória\r\nA virgem Maria vai interceder\r\n\r\nE vai acontecer, o novo pentecostes(3x)\r\n\r\nFogo fogo fogo fogo fogo fogo fogo do céu(4x)\r\n\r\nPosso dançar no teu espírito (posso dançar)\r\nPosso pular com teu poder (posso pular)\r\nPosso correr na tua unção\r\nPosso gritar a tua glória\r\n\r\nFogo fogo fogo fogo fogo fogo fogo do céu(4x)', '2026-09-30 13:28:28', '2026-09-30 13:28:28');

-- --------------------------------------------------------

--
-- Estrutura para tabela `campistas_historico`
--

CREATE TABLE `campistas_historico` (
  `id` int(11) NOT NULL,
  `pessoa_id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `cor_tribo_time` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cronograma`
--

CREATE TABLE `cronograma` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `dia_numero` int(11) NOT NULL,
  `horario_inicio` time NOT NULL,
  `duracao_minutos` int(11) NOT NULL,
  `roteiro_atividade` varchar(255) NOT NULL,
  `equipes_responsaveis` varchar(255) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `cor_destaque` varchar(20) DEFAULT 'padrao'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cronograma`
--

INSERT INTO `cronograma` (`id`, `edicao_id`, `dia_numero`, `horario_inicio`, `duracao_minutos`, `roteiro_atividade`, `equipes_responsaveis`, `detalhes`, `ordem`, `cor_destaque`) VALUES
(4, 2, 0, '00:00:07', 30, 'sssss', 'Todas as Equipes', 'ggggg', 0, 'padrao');

-- --------------------------------------------------------

--
-- Estrutura para tabela `edicoes`
--

CREATE TABLE `edicoes` (
  `id` int(11) NOT NULL,
  `modalidade_id` int(11) NOT NULL,
  `ano` int(11) DEFAULT NULL,
  `nome` varchar(255) NOT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `ativa` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `edicoes`
--

INSERT INTO `edicoes` (`id`, `modalidade_id`, `ano`, `nome`, `data_inicio`, `data_fim`, `ativa`, `criado_em`, `status`) VALUES
(2, 2, 2026, '2° CES', '2026-07-10', '2026-07-12', 1, '2026-09-30 12:17:17', 'ativa'),
(3, 2, 2027, '3° CES', NULL, NULL, 1, '2026-09-30 14:03:30', 'ativa'),
(4, 3, 2026, '2° MIRIM', NULL, NULL, 1, '2026-09-30 14:03:45', 'ativa');

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipes_campistas`
--

CREATE TABLE `equipes_campistas` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `cor` varchar(50) DEFAULT '#00bcd4',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `equipes_campistas`
--

INSERT INTO `equipes_campistas` (`id`, `edicao_id`, `nome`, `cor`, `created_at`) VALUES
(1, 2, 'COMUNIDADE PIEDADE - COR BRANCA', '#ffffff', '2026-09-30 16:51:35'),
(2, 2, 'COMUNIDADE TEMOR DE DEUS - COR AZUL', '#108bea', '2026-09-30 16:52:55'),
(3, 2, 'COMUNIDADE FORTALEZA - COR ROSA', '#ec41b6', '2026-09-30 16:53:20'),
(4, 2, 'COMUNIDADE PIEDADE - COR BRANCA', '#0a8529', '2026-10-02 12:26:33'),
(5, 2, 'COMUNIDADE FORTALEZA - COR ROSA', '#00bcd4', '2026-10-02 12:34:11'),
(6, 4, 'TRIBO SANTA TERESINHA DO MENINO JESUS', '#c563ab', '2026-10-09 11:24:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipes_lideres`
--

CREATE TABLE `equipes_lideres` (
  `id` int(11) NOT NULL,
  `equipe_id` int(11) NOT NULL,
  `cargo` varchar(100) DEFAULT 'Confidente',
  `nome` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `equipes_lideres`
--

INSERT INTO `equipes_lideres` (`id`, `equipe_id`, `cargo`, `nome`, `created_at`) VALUES
(1, 1, 'Confidente', 'Thomaz', '2026-09-30 16:51:50'),
(2, 1, 'Confidente', 'Amanda', '2026-09-30 16:51:56'),
(3, 1, 'Confidente', 'Rosângela', '2026-09-30 16:52:04'),
(4, 1, 'Confidente', 'Gustavo', '2026-09-30 16:52:10');

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipes_membros`
--

CREATE TABLE `equipes_membros` (
  `id` int(11) NOT NULL,
  `equipe_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `equipes_membros`
--

INSERT INTO `equipes_membros` (`id`, `equipe_id`, `nome`, `created_at`) VALUES
(16, 2, 'Eliana Franco', '2026-10-09 11:04:56'),
(18, 6, 'Ana luiza', '2026-10-09 11:25:30'),
(19, 1, 'João Miguel Rampasso', '2026-10-16 14:15:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipes_trabalho`
--

CREATE TABLE `equipes_trabalho` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `atribuicoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `equipes_trabalho`
--

INSERT INTO `equipes_trabalho` (`id`, `edicao_id`, `nome`, `atribuicoes`, `criado_em`) VALUES
(1, 2, 'Intercessão', '- Orações pré-acampamento.\r\n- Quinta-feira estar mais cedo no sítio para as bênçãos das vivências.\r\n- Sexta-feira, oração da manhã com todos na capela (diretores vão passar horário e cronograma).\r\n- Orações fervorosas durante os momentos, pregações e vivências, podendo ficar sempre cinco em cada pregação ou momento (fazer revezamento).\r\n- O Senhor ficará exposto para adoração/orações durante todo o dia, sendo devolvido ao sacrário depois das orações de renúncia.\r\n- Aproveitar momentos mais tranquilos para fazer partilha de vida entre todos os intercessores.\r\n- Haverá efusão na capela. O padre conduzirá e os intercessores clamam e oram pelo Espírito (terá instruções sobre o que fazer durante a efusão).\r\n- Não haverá necessidade de descer para as vivências.\r\n- Haverá momento mariano no qual precisa de várias Marias (ver quantidade de vestes e tamanho).\r\n- Levar tudo que necessita para o sítio( levar Jesus na custódia).\r\n- Outros momentos específicos os diretores vão informar.\r\n- A capela é restrita aos intercessores (pessoas ou equipe entram conforme o cronograma ou permissão dos diretores).\r\n- Os diretores entraram antes de fazer suas pregações para receber as orações (Se necessário, lembrá-los devido a correria deles).\r\n- Faz parte da responsabilidade da capela organizar tudo para a missa e servir (comunicar os ministros da capela e os acólitos) \r\n- Preparações das preces para a Santa Missa (mandar para a secretaria imprimir).\r\n- Ficar atento aos momentos de alimentação, sempre deixando dois com Jesus.\r\n- Caso o Senhor mostre algo que seja discernido, poderá ser levado aos diretores.\r\n- Colocar o nome de cada campista aos pés do Senhor, apresentá-los a Jesus levando por comunidade nome por nome.\r\n- Fazer momentos de orações apresentando ao Senhor o nome dos confidentes, aliviando assim o peso da missão.\r\n- Ao término das atividades, no domingo poderão iniciar os trabalhos de limpeza e organização da capela, assim finalizando as atividades da equipe.\r\n- O banho da equipe será nos horários da vivência na mata (fazer revezamento).', '2026-09-30 12:59:40'),
(2, 2, 'Secretaria', 'Providenciar crachás.\r\nPregação: Imagem e semelhança: Estar com as tintas preparadas, coloridas e brancas.\r\nBolsa para vivências.\r\nOrganização das pastas das vivências.\r\nBuscar frutas para as vivências com a cozinha.\r\nComprar as uvas.\r\nEntregar as uvas para a manutenção.\r\nMomento da promessa: Bexiga com as promessas dentro.\r\nMissas: Impressão das preces e leituras.\r\nMomento dos dons do Espírito Santo: Plaquinhas com significados.\r\nTalento: Caixa de presente bem enfeitada e bonita.\r\nCaixa para Bíblias por comunidade.\r\nEstar sempre preparado com vendas, papel e caneta.\r\nPregação da Bíblia: Caça ao tesouro (Lugares simples para enterrar, embalar as Bíblias para presente e organizar as pistas).\r\nMomento da salvação: Roupa de Jesus e Maria.\r\nEnfeitar a entrada da capela para o céu e o inferno.\r\nMomento dos dois caminhos: Participação no inferno.\r\nProvidenciar sacos de lixo para colocar as roupas sujas dos campistas.\r\nEntregar cartinhas no momento das cartinhas.\r\nPregação Indelével: Pedra para cada um (Lembrando que a marca de óleo não pode sair) (Ver se consegue perfumar o óleo.)\r\nIr atrás de todo o material necessário e impressora.', '2026-09-30 13:04:55'),
(3, 2, 'Direção', '', '2026-10-07 11:44:06'),
(4, 4, 'Música', NULL, '2026-10-09 11:22:46');

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipe_geral`
--

CREATE TABLE `equipe_geral` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `tamanho_camiseta` varchar(10) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `pessoa_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `equipe_geral`
--

INSERT INTO `equipe_geral` (`id`, `edicao_id`, `nome`, `telefone`, `tamanho_camiseta`, `criado_em`, `pessoa_id`) VALUES
(1, 2, 'Sophia Christofole Rampasso', '18 99786-9866', 'PP', '2026-09-30 12:59:26', NULL),
(2, 2, 'Elisangela Christofole', '18 99789-5455', 'GG', '2026-09-30 13:04:27', NULL),
(3, 2, 'Kaio Gabriel Alves Figueredo', '18 99674-2013', 'M', '2026-09-30 13:04:45', NULL),
(5, 2, 'Luma Christofole Massaranduba', '18996678909', 'G', '2026-10-07 11:43:38', NULL),
(6, 2, 'Eliana Franco', '18 99764-9802', 'M', '2026-10-09 10:55:03', NULL),
(7, 4, 'Elisangela Christofole', '18 99789-5455', 'GG', '2026-10-09 11:22:31', NULL),
(8, 4, 'João Miguel Rampasso', '18 998648300', 'G', '2026-10-16 14:15:46', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipe_membros`
--

CREATE TABLE `equipe_membros` (
  `id` int(11) NOT NULL,
  `equipe_id` int(11) NOT NULL,
  `voluntario_id` int(11) NOT NULL,
  `e_coordenador` tinyint(1) DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `equipe_membros`
--

INSERT INTO `equipe_membros` (`id`, `equipe_id`, `voluntario_id`, `e_coordenador`, `criado_em`) VALUES
(2, 2, 2, 1, '2026-09-30 13:05:07'),
(3, 2, 3, 0, '2026-09-30 13:05:11'),
(4, 1, 1, 0, '2026-10-07 10:58:42'),
(6, 3, 6, 0, '2026-10-09 10:55:52'),
(7, 4, 7, 0, '2026-10-09 11:22:54'),
(8, 4, 8, 0, '2026-10-16 14:15:57');

-- --------------------------------------------------------

--
-- Estrutura para tabela `espiritualidades`
--

CREATE TABLE `espiritualidades` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `data_momento` date DEFAULT NULL,
  `horario_momento` time DEFAULT NULL,
  `animacao_responsavel` varchar(255) DEFAULT NULL,
  `oracao_inicial_responsavel` varchar(255) DEFAULT NULL,
  `pregacao_tema` varchar(255) DEFAULT NULL,
  `pregacao_pregador` varchar(255) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `espiritualidades`
--

INSERT INTO `espiritualidades` (`id`, `edicao_id`, `tipo`, `data_momento`, `horario_momento`, `animacao_responsavel`, `oracao_inicial_responsavel`, `pregacao_tema`, `pregacao_pregador`, `detalhes`, `created_at`) VALUES
(1, 2, 'Espiritualidade', '2026-09-30', '19:30:00', 'Recreação', 'Luma', 'Imagem e semelhança', 'Marluse', 'Teatro das cores;\r\nDinâmica das tintas;\r\nRecados sobre as orações de preparação;\r\nRecados coordenação.', '2026-10-07 16:43:25'),
(4, 4, 'Espiritualidade', '2026-10-13', '19:30:00', 'Recreação', 'Luma', 'Maria - exemplo de servir', 'Luma', 'Animação com a música \"Fogo do Espírito\";\r\nEntrada de uma pessoa vestida de Nossa Senhora e depois Adoração ao Santíssimo Sacramento.\r\nPrimeiros recados com a coordenação.', '2026-10-09 10:34:30');

-- --------------------------------------------------------

--
-- Estrutura para tabela `espiritualidade_presencas`
--

CREATE TABLE `espiritualidade_presencas` (
  `id` int(11) NOT NULL,
  `espiritualidade_id` int(11) NOT NULL,
  `membro_id` int(11) NOT NULL,
  `status` enum('presente','ausente') DEFAULT 'presente',
  `justificativa` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `espiritualidade_presencas`
--

INSERT INTO `espiritualidade_presencas` (`id`, `espiritualidade_id`, `membro_id`, `status`, `justificativa`, `created_at`) VALUES
(1, 1, 6, 'ausente', 'aniversário da mãe', '2026-10-16 14:40:05'),
(2, 1, 2, 'presente', '', '2026-10-16 14:40:05'),
(3, 1, 3, 'presente', '', '2026-10-16 14:40:05'),
(4, 1, 5, 'presente', '', '2026-10-16 14:40:05'),
(5, 1, 1, 'ausente', '', '2026-10-16 14:40:05');

-- --------------------------------------------------------

--
-- Estrutura para tabela `missas`
--

CREATE TABLE `missas` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `dia_missa` enum('1º Dia','2º Dia','3º Dia','4º Dia') NOT NULL DEFAULT '1º Dia',
  `horario` time DEFAULT NULL,
  `leituras` text DEFAULT NULL,
  `preces` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `missas`
--

INSERT INTO `missas` (`id`, `edicao_id`, `titulo`, `dia_missa`, `horario`, `leituras`, `preces`, `created_at`) VALUES
(1, 2, 'Espírito Santo', '2º Dia', '07:50:00', 'Evangelho (Jo 20, 19-23)\r\n\r\nLeitura do livro de São João\r\n\r\nAo anoitecer daquele dia, o primeiro da semana, estando fechadas, por medo dos judeus, as portas do lugar onde os discípulos se encontravam, Jesus entrou e pondo-se no meio deles, disse: “A paz esteja convosco”.\r\n\r\nDepois destas palavras, mostrou-lhes as mãos e o lado. Então os discípulos se alegraram por verem o Senhor.\r\n\r\nNovamente, Jesus disse: “A paz esteja convosco. Como o Pai me enviou, também eu vos envio”.\r\n\r\nE depois de ter dito isto, soprou sobre eles e disse: “Recebei o Espírito Santo. A quem perdoardes os pecados, eles lhes serão perdoados; a quem os não perdoardes, eles lhes serão retidos”.', 'A RESPOSTA DAS PRECES SERÁ:\r\n\r\nVinde, Espírito Santo!\r\n\r\n- Para que o Espírito Santo seja o nosso guia, em todos os caminhos e a todos os momentos, nos mantendo firmes na caminhada, rezemos…\r\n\r\n- Para que o Espírito Santo nos conceda os dons que necessitamos para sermos instrumentos nas obras de Deus, e seguirmos evangelizando o mundo, rezemos…\r\n\r\n- Para que estejamos abertos as mudanças necessárias que o Espírito Santo promove em nossas vidas e que nos levam cada vez mais para perto de Jesus, rezemos…\r\n\r\n- Para que possamos abrir o coração e receber a unção do Espírito Santo e sermos verdadeiramente confirmados neste sacramento, rezemos…', '2026-09-30 14:16:21');

-- --------------------------------------------------------

--
-- Estrutura para tabela `modalidades`
--

CREATE TABLE `modalidades` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `lema` varchar(255) DEFAULT NULL,
  `publico_alvo` varchar(255) DEFAULT NULL,
  `cor_tema` varchar(7) DEFAULT NULL,
  `icone` varchar(50) DEFAULT NULL,
  `cor` varchar(20) DEFAULT NULL,
  `bg_tag` varchar(20) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `modalidades`
--

INSERT INTO `modalidades` (`id`, `nome`, `descricao`, `lema`, `publico_alvo`, `cor_tema`, `icone`, `cor`, `bg_tag`, `criado_em`) VALUES
(2, 'CES', 'Acampamento do Crisma', 'Confirmados no Espírito Santo', 'Crsimandos', NULL, NULL, NULL, NULL, '2026-09-30 11:10:24'),
(3, 'MIRIM', NULL, 'Eu sou um sonho de Deus', 'Catequizandos da eucaristia', NULL, NULL, '#e5c110', NULL, '2026-09-30 11:59:54'),
(4, 'PAC', NULL, 'Pais de Adolescentes Cristões', 'Pais dos campistas do FAC', NULL, NULL, '#2fa1f9', NULL, '2026-09-30 12:01:22'),
(5, 'Mannaim', NULL, 'Onde os anjos acampam', '6 a 10', NULL, NULL, '#0452b9', NULL, '2026-09-30 14:26:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos_customizados`
--

CREATE TABLE `modulos_customizados` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `icone` varchar(50) DEFAULT NULL,
  `conteudo_html` longtext DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `musicas`
--

CREATE TABLE `musicas` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `cantor_banda` varchar(100) DEFAULT NULL,
  `categoria` enum('TEMA','MISSA','MOMENTOS') DEFAULT 'MOMENTOS',
  `letra_cifra` longtext DEFAULT NULL,
  `link_audio` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pessoas`
--

CREATE TABLE `pessoas` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `tamanho_camiseta` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `campista_origem` text DEFAULT NULL,
  `equipe_trabalho_origem` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pessoas`
--

INSERT INTO `pessoas` (`id`, `nome`, `telefone`, `data_nascimento`, `foto`, `tamanho_camiseta`, `created_at`, `campista_origem`, `equipe_trabalho_origem`) VALUES
(1, 'Elisangela Christofole', '18 99789-5455', '1980-03-01', 'uploads/perfis/perfil_1791372710_7242.jpg', 'GG', '2026-10-07 11:31:50', 'Sênior 2025 - Levi', NULL),
(3, 'Kaio Gabriel Alves Figueredo', '', NULL, 'uploads/perfis/perfil_1791373839_3222.png', NULL, '2026-10-07 11:50:39', '', ''),
(4, 'Sophia Christofole Rampasso', '18 99674-2013', '2010-04-22', 'uploads/perfis/perfil_1791373979_7391.png', NULL, '2026-10-07 11:52:59', 'FAC 2025', 'CES 2025 - CONFIDENTE\r\nPAC 2025 - EXTERNA\r\nMIRIM 2025 - GUARDIÃO\r\nMANAAIM 2026 - ANJO\r\nCES 2026 - INTERCESSÃO'),
(5, 'Eliana Franco', '18 99764-9802', NULL, NULL, NULL, '2026-10-09 10:55:03', NULL, NULL),
(6, 'Eliana Franco', NULL, NULL, NULL, NULL, '2026-10-09 11:04:56', NULL, NULL),
(7, 'Elisangela Christofole', NULL, NULL, NULL, NULL, '2026-10-09 11:24:42', NULL, NULL),
(8, 'Elisangela Christofole', NULL, NULL, NULL, NULL, '2026-10-09 11:25:12', NULL, NULL),
(9, 'Ana luiza', NULL, NULL, NULL, NULL, '2026-10-09 11:25:30', NULL, NULL),
(10, 'João Miguel Rampasso', '18 998648300', NULL, NULL, NULL, '2026-10-16 14:15:04', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `pregacoes_dinamicas`
--

CREATE TABLE `pregacoes_dinamicas` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  `tema` varchar(255) DEFAULT NULL,
  `pregacao` text DEFAULT NULL,
  `orientacoes` text DEFAULT NULL,
  `dinamica_titulo` varchar(255) DEFAULT NULL,
  `dinamica_desc` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pregacoes_dinamicas`
--

INSERT INTO `pregacoes_dinamicas` (`id`, `edicao_id`, `ordem`, `tema`, `pregacao`, `orientacoes`, `dinamica_titulo`, `dinamica_desc`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'O Amor de Deus', 'Deus tem um amor imenso e particular por cada um de nós, de forma totalmente especial. Por causa desse amor, Ele criou o homem à sua imagem e semelhança. Ele não apenas nos criou, mas soprou sobre nós o seu próprio Espírito.\r\nEsse mesmo Espírito Santo, no momento do nosso batismo, nos adotou e nos fez verdadeiros filhos de Deus. Diante disso, fica a pergunta: existe amor maior do que esse? Ele nos entregou o que tinha de mais valioso: o seu próprio Espírito, que é Quem nos dá a vida e nos santifica.\r\nO Espírito Santo é Aquele que dá sabor às nossas ações e às nossas orações, transformando o nosso dia a dia. Podemos fazer uma relação disso com o pirulito que vocês receberam: assim como o pirulito vai mudando o sabor da boca e trazendo doçura à medida que o consumimos, a presença do Espírito Santo na nossa vida espiritual dá um sabor inteiramente novo, doce e especial a tudo o que fazemos e rezamos.', '', 'Pirulito', 'Todos os campistas receberão o pirulito assim que subir ao ônibus para ir para o sítio. Eles terão livre decisão como utilizar o pirulito. Após a pregação, o pregador fará uma relação do doce do pirulito. O sabor que representa o amor, que nós colocamos em tudo que nós fazemos para Deus. Quando fazemos pelo Espírito Santo, sentimos o sabor de trabalhar com e para o irmão, ao fazer tudo por amor. Nós sentimos a presença de Deus. Assim como nós sentimos o seu amor, nós também sentimos o doce do pirulito, podemos ter o pirulito em mãos e não sentir seu sabor se não tirarmos o papel, assim fazemos quando recebemos Ele em nossa alma, mas não clamamos sua presença. Fazer relação como eles utilizaram o pirulito, falando do amor e Espírito de Deus.', '2026-09-30 13:49:38', '2026-09-30 13:49:38'),
(3, 2, 3, 'Fé e Eucaristia', 'Pregação baseada em um testemunho sobre a Eucaristia. A Eucaristia é a força que nos faz perseverar na fé e seguir até o céu. Levar os campistas a refletirem como estão em comunhão com a Eucaristia. Como estão em comunhão com Cristo? Como está a sua confissão? A pregação deve ser catequética. Levar os catequizandos a compreender a presença real de Cristo em corpo, sangue, alma e divindade. Levá-los a ter desejo pela comunhão. Ter desejo de viver a santidade para sempre estar em comunhão com Cristo.', '', '', '', '2026-09-30 13:53:31', '2026-09-30 13:53:31');

-- --------------------------------------------------------

--
-- Estrutura para tabela `recreacao`
--

CREATE TABLE `recreacao` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) DEFAULT 0,
  `tipo` enum('brincadeira','teatro','olimpiada') NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `explicacao` text DEFAULT NULL,
  `momento` varchar(255) DEFAULT NULL,
  `texto_teatro` longtext DEFAULT NULL,
  `tema` varchar(255) DEFAULT NULL,
  `personagens` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `recreacao`
--

INSERT INTO `recreacao` (`id`, `edicao_id`, `tipo`, `nome`, `explicacao`, `momento`, `texto_teatro`, `tema`, `personagens`, `created_at`) VALUES
(6, 2, 'teatro', 'Talentos', NULL, '2º dia - Pregação Dons e Talentos', 'Parábola dos talentos- Jornal Cesional.\r\n\r\n\r\nInicia-se o teatro com os bastidores, onde o diretor anuncia que vai começar o jornal e faz a contagem regressiva. Enquanto isso, os maquiadores estão em ação também, bem como os câmeras se posicionando e o pessoal da redação ao fundo. \r\nNo “um”, os apresentadores Patrícia Poesia e James Bonner se apresentam e desejam boa noite. Em seguida, eles anunciam aleatoriamente três notícias referente ao acompanhamento, em forma de brincadeira, para já animar o teatro. Assim, toca-se a música da abertura do jornal nacional.\r\nE assim segue:\r\n\r\nPatrícia: O jornal CESional de hoje vem falar sobre uma das muitas parábolas usadas por Jesus. Trata-se da parábola dos talentos, segundo o evangelho de São Mateus 25,14-30.\r\n\r\nBonner: Conta-se sobre um homem que, ao partir em viagem, chamou três de seus servos para lhe confiar seus bens. A um entregou 5 talentos, a outro 2 e a outro a quantia de 1 talento, segundo a capacidade de cada um. Assim, ele pode viajar tranquilo, confiante no que seus servos iriam fazer. \r\nAo retornar da viagem, chamou os três para que prestassem contas de seus bens que lhes foram confiados. Aquele primeiro que recebeu 5 talentos trabalhou muito, negociou o dinheiro e lucrou mais 5, devolvendo a seu senhor a quantia de 10 talentos. Por ser bom e fiel, ele foi recompensado com muitas grandezas. Da mesma maneira, foi recompensado o segundo servo, pois também se dedicou muito, negociou seus 2 talentos e lucrou mais 2. Ambos compartilharam da alegria do seu senhor, que sabia que podia confiar mais neles. Já o terceiro servo, o qual recebera um talento, temendo seu Senhor por ser um homem muito severo, ficou desanimado e escondeu o dinheiro, devolvendo-o. Seu senhor ficou muito triste e irritado com a falta de esforço e criatividade do seu servo inútil e preguiçoso, e então, lhe retirou esse único talento e deu ao outro servo. Chateado por não poder confiar nele, o mandou embora.\r\n\r\nMas padre, eu não entendi uma coisa na parábola: Por que os servos receberam quantidades diferentes de talentos?\r\nSimplesmente porque os dons não são os mesmos para todos, não são na mesma medida. Se fosse assim, todos nós seríamos iguais e ninguém precisaria de ninguém. Cada um de nós tem dons diferentes. Essas diferenças pertencem aos planos de Deus, que quer que cada um receba de outra pessoa aquilo que precisa.\r\n\r\nEntão, nós temos que seguir o exemplo dos outros dois servos que souberam trabalhar seus talentos?\r\nSim, Deus nos dá os dons, mas cabe a cada um de nós se esforçar para desenvolvê-los e multiplicá-los. Esforço e dedicação são fundamentais para a gente vencer e contar com as bênçãos de Deus que nos faz cheios de qualidades e dons.\r\n\r\n\r\nPadre, nós temos uma ouvinte na outra língua que tem uma dúvida. Vamos ouvi-la:\r\n\r\n\r\nJornalista a saúda e pede que faça a sua pergunta ao Padre.\r\n\r\nPadre, acho que não tenho nenhum dom. Sabe, eu vou à catequese, vou às missas todos os finais de semanas e vejo que crianças que têm muitos dons e participam das atividades na igreja. Eu também gostaria de participar! A catequista me chamou algumas vezes para fazer as leituras na missa, mas eu tenho muita vergonha. Eu também tenho muita vontade de cantar. Certa vez, pedi para cantar no coral infantil, mas percebi o quanto sou desafinada, então desisti.\r\nMas minha filha, é normal que algumas pessoas tenham alguns dons que outras não tenham. Isso não significa que algumas sejam melhores ou tenham mais sorte que as outras. Então, não se julgue nem se condene se você não tem o dom para encenar nem para cantar.\r\nAcho que estou entendendo o que o senhor quer dizer…\r\n Que bom! Olhe, ser diferente não é um mal, mas mostra que cada um tem o seu próprio valor. De uma coisa eu tenho certeza: Outros dons você tem, mas estão escondidinhas dentro de você por causa dessa sua frustração e timidez. E Deus está muito triste com isso. Pare e pense. O que você gosta de fazer?\r\nHum… deixe-me pensar. Ah, eu gosto muito de brincar com as crianças da minha rua.\r\nEntão! Sabia que, sendo assim, você pode trabalhar na infância missionária, por exemplo? \r\nEu?\r\nSim! Você gosta de brincar, pode ajudar nos momentos de recreação da infância missionária.\r\nNossa, eu nunca tinha pensado nisso.\r\nEsse é um dos dons que Deus lhe confiou, e você tem que usá-lo para produzir frutos. Mas eu acredito que você tenha mais dons.\r\nAgora eu acredito também. Vou tentar descobrir no que eu posso ser útil para Deus sempre fazendo o melhor.\r\nIsso mesmo! Preste bem atenção no que você gosta de fazer e como você pode usar esses talentos.\r\nE como posso fazer isso, padre?\r\nOre e peça o Espírito Santo para que ele lhe revele seus dons. Você também pode pedir ajuda ao padre e aos catequistas.\r\n Você, minha filha, é única e especial para Deus. Ele te deu dons especiais e diferentes para serem desenvolvidos e usados, mesmo ainda sendo uma criança.\r\n\r\n\r\nEla fica muito feliz e agradece ao padre e se despede. Os jornalistas também agradecem a participação deles e se despedem.\r\n\r\n\r\nPensando na história da parábola dos talentos, o jornal Cesional foi atrás e trouxe uma cena como essa história acontece hoje em nossa realidade. Vejamos essa reportagem feita há dois meses atrás pelo repórter _______. Na paróquia Nossa Senhora Aparecida na cidade de Regente Feijó. \r\n\r\nO repórter está na paróquia, entrevistou o padre _______. Este vai fazer uma viagem de dois meses à Itália, então chama três de seus paroquianos e lhes confia alguns de seus bens. Ao primeiro, que tinha o dom de tocar, o padre lhe confia seu violão. Ao segundo, que tinha o dom de cozinhar, o padre lhe confiou sua panela. Ao terceiro, que tinha o dom de evangelizar, o padre lhe confiou sua Bíblia. O padre enfatiza que confia muito neles e sabe que está deixando seus bens com as pessoas certas. \r\nAssim, o repórter encerra a reportagem.\r\n\r\n\r\nBonner: Dois meses se passaram e hoje o nosso repórter, _______, está ao vivo em Regente Feijó e mostra como foi a volta do padre, _______. É com você, _______.\r\n\r\n\r\nPatrícia: Agora nós falaremos ao vivo e direto da Itália com nosso embaixador no Vaticano, Padre Fábio de Melo.\r\n\r\n\r\nO recreador que faz o que está escondido com o microfone. Eles se saúdam e começam a entrevista onde os jornalistas se revezam nas perguntas.\r\n\r\n\r\nPadre Fábio, o que vem a ser os talentos que o patrão confiou aos servos?\r\nTalento era uma unidade de peso muito usada naquela época. Era o equivalente a mais ou menos 35 kg de ouro. Portanto, era muito dinheiro. Ganhar um talento era ganhar um dinheirão.\r\n\r\nO que o senhor tem a dizer sobre a atitude preguiçosa do terceiro servo?\r\nEle ficou muito desanimado quando recebeu o talento. E quem é desanimado arruma desculpa para tudo. Então ele pensou: E se eu aplicar esse dinheiro e não tiver lucro? E se eu for roubado? E se não der certo? O que eu vou dizer depois ao patrão? Foi aí que ele teve a ideia maluca de enterrar o dinheiro.\r\n\r\nEntão, se eu entendi bem, o grande problema da história foi o desânimo desse servo?\r\nSim, o desânimo é um mal terrível que faz a gente cruzar os braços e ficar parado na vida. Assim a gente não cresce e não se desenvolve. Por isso as coisas que causam desânimo em nós precisam ser vencidas.\r\n\r\nMas só o desânimo nos impede de usarmos nossos dons?\r\nNão! Além do desânimo e da falta de vontade, também a inferioridade quando nós comparamos e achamos os outros melhores que nós; a timidez, a vergonha, o medo e a preguiça da vocação, mais grave que a preguiça física.\r\n\r\nPadre, hoje nós usamos a expressão talento referindo-se aos dons que temos, não é?\r\nIsso mesmo! Cada um de nós tem muitos talentos, dons que Deus nos dá, capacidades e valores que nos caracterizam. E todos os têm, viu? Deus não deixa ninguém sem dom. Muitas pessoas acham que não têm dons, mas isso é um grande e perigoso engano.\r\n\r\n\r\nO repórter está na paróquia e novamente entrevista o padre _______. Este voltou de viagem e chama os três paroquianos para ver como ficarão os bens confiados.\r\nO primeiro entra tocando violão com algumas crianças cantando e diz: Eu sei do grande amor que o senhor tem pela música e pela preocupação em formar novos músicos para nossa igreja. Então usei seu violão para ensinar algumas crianças da catequese a cantar.\r\nO padre fica muito maravilhado, então pede para que fique com o violão e o deixa responsável para que continue ensinando ensinando as crianças.\r\nO segundo entra com a panela e diz: Eu sei do grande carinho que o senhor tem para com essa panela e com todos os utensílios da paróquia. Então eu usei para cozinhar no acampamento e na festa da paróquia.\r\nO padre fica muito maravilhado também, então pede para que fique com a panela e continue cozinhando com ela nos eventos da paróquia.\r\nO terceiro chega em silêncio, cabisbaixo, e o padre pergunta o que fez com a Bíblia. Ele responde: Eu sei que o senhor é uma pessoa muito cuidadosa e exigente, por isso fiquei com medo de usar a Bíblia e estragá-la ou perdê-la. Então guardei em um lugar seguro. Ela está novinha do jeito que o senhor me entregou.\r\nO padre fica muito irritado e chateado, diz que não deu a Bíblia para que fosse guardada, mas sim para que usasse para evangelizar as pessoas. Muito triste e arrependido, ele pede perdão ao padre e promete que vai mudar, que nunca mais guardará o que lhe for confiado.\r\n O repórter encerra a reportagem.\r\n\r\n\r\nPatrícia: E assim nós terminamos nossa reportagem de hoje, onde Jesus vem nos deixar a mensagem que todos nós temos dons diferentes, o que nos torna diferente, mas temos a mesma importância diante dele.\r\n\r\nBonner: Que sejamos como os servos que não tiveram preguiça a fim de que, como eles, tenhamos nossos talentos multiplicados. Tenham todos uma boa noite.\r\nPatrícia: Boa noite e até amanhã.\r\n\r\n\r\nVolta a tocar a música e todos entram em ação como no início, enquanto os jornalistas se levantam e saem de cena. Detalhe: Eles estão bem arrumados somente da cintura para cima, para baixo eles usam short e chinelo. \r\n\r\nFIM.', NULL, NULL, '2026-10-09 11:56:48');

-- --------------------------------------------------------

--
-- Estrutura para tabela `reuniao_momentos`
--

CREATE TABLE `reuniao_momentos` (
  `id` int(11) NOT NULL,
  `reuniao_id` int(11) NOT NULL,
  `horario` time DEFAULT NULL,
  `titulo` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ordem` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `reunioes`
--

CREATE TABLE `reunioes` (
  `id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `numero_encontro` int(11) DEFAULT NULL,
  `data_reuniao` datetime DEFAULT NULL,
  `responsavel_animacao` varchar(100) DEFAULT NULL,
  `responsavel_oracao` varchar(100) DEFAULT NULL,
  `pregador` varchar(100) DEFAULT NULL,
  `tema_pregacao` varchar(150) DEFAULT NULL,
  `roteiro_vivencia` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `setores`
--

CREATE TABLE `setores` (
  `id` int(11) NOT NULL,
  `modalidade_id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `atribuicoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('COORDENADORA_GERAL','COORDENADOR','DIRETOR') DEFAULT 'COORDENADOR',
  `modalidade_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `perfil`, `modalidade_id`, `ativo`, `criado_em`) VALUES
(1, 'Valéria Guimarães', 'valeria@geral.com', '$2y$10$/xFg036.SFA.4/4T91eeFuqo6ZIY9PIuQnErbUfbvMU1m0YvZqk/W', 'COORDENADORA_GERAL', NULL, 1, '2026-09-30 11:25:55'),
(4, 'Luma Massaranduba', 'luma@ces.com', '$2y$10$LaEprcMb7Y0rJBCpOTAeF.MaXJeIt0sMYVtZe6M.bYxnWupDnequq', 'DIRETOR', NULL, 1, '2026-09-30 12:10:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_modalidade`
--

CREATE TABLE `usuario_modalidade` (
  `usuario_id` int(11) NOT NULL,
  `modalidade_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuario_modalidade`
--

INSERT INTO `usuario_modalidade` (`usuario_id`, `modalidade_id`) VALUES
(4, 2),
(4, 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `voluntarios`
--

CREATE TABLE `voluntarios` (
  `id` int(11) NOT NULL,
  `modalidade_id` int(11) NOT NULL,
  `edicao_id` int(11) NOT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `tamanho_camiseta` enum('PP','P','M','G','GG','EXG') DEFAULT 'M',
  `funcao` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `apostilas_musicas`
--
ALTER TABLE `apostilas_musicas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_apostilas_edicoes` (`edicao_id`);

--
-- Índices de tabela `campistas_historico`
--
ALTER TABLE `campistas_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pessoa_id` (`pessoa_id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `cronograma`
--
ALTER TABLE `cronograma`
  ADD PRIMARY KEY (`id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `edicoes`
--
ALTER TABLE `edicoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modalidade_id` (`modalidade_id`);

--
-- Índices de tabela `equipes_campistas`
--
ALTER TABLE `equipes_campistas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `equipes_lideres`
--
ALTER TABLE `equipes_lideres`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipe_id` (`equipe_id`);

--
-- Índices de tabela `equipes_membros`
--
ALTER TABLE `equipes_membros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipe_id` (`equipe_id`);

--
-- Índices de tabela `equipes_trabalho`
--
ALTER TABLE `equipes_trabalho`
  ADD PRIMARY KEY (`id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `equipe_geral`
--
ALTER TABLE `equipe_geral`
  ADD PRIMARY KEY (`id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `equipe_membros`
--
ALTER TABLE `equipe_membros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipe_id` (`equipe_id`),
  ADD KEY `voluntario_id` (`voluntario_id`);

--
-- Índices de tabela `espiritualidades`
--
ALTER TABLE `espiritualidades`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `espiritualidade_presencas`
--
ALTER TABLE `espiritualidade_presencas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_presenca` (`espiritualidade_id`,`membro_id`);

--
-- Índices de tabela `missas`
--
ALTER TABLE `missas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `modalidades`
--
ALTER TABLE `modalidades`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `modulos_customizados`
--
ALTER TABLE `modulos_customizados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `musicas`
--
ALTER TABLE `musicas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `pessoas`
--
ALTER TABLE `pessoas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `pregacoes_dinamicas`
--
ALTER TABLE `pregacoes_dinamicas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pregacoes_edicoes` (`edicao_id`);

--
-- Índices de tabela `recreacao`
--
ALTER TABLE `recreacao`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `reuniao_momentos`
--
ALTER TABLE `reuniao_momentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reuniao_id` (`reuniao_id`);

--
-- Índices de tabela `reunioes`
--
ALTER TABLE `reunioes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `setores`
--
ALTER TABLE `setores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modalidade_id` (`modalidade_id`),
  ADD KEY `edicao_id` (`edicao_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `modalidade_id` (`modalidade_id`);

--
-- Índices de tabela `usuario_modalidade`
--
ALTER TABLE `usuario_modalidade`
  ADD PRIMARY KEY (`usuario_id`,`modalidade_id`),
  ADD KEY `modalidade_id` (`modalidade_id`);

--
-- Índices de tabela `voluntarios`
--
ALTER TABLE `voluntarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modalidade_id` (`modalidade_id`),
  ADD KEY `edicao_id` (`edicao_id`),
  ADD KEY `setor_id` (`setor_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `apostilas_musicas`
--
ALTER TABLE `apostilas_musicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `campistas_historico`
--
ALTER TABLE `campistas_historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cronograma`
--
ALTER TABLE `cronograma`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `edicoes`
--
ALTER TABLE `edicoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `equipes_campistas`
--
ALTER TABLE `equipes_campistas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `equipes_lideres`
--
ALTER TABLE `equipes_lideres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `equipes_membros`
--
ALTER TABLE `equipes_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `equipes_trabalho`
--
ALTER TABLE `equipes_trabalho`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `equipe_geral`
--
ALTER TABLE `equipe_geral`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `equipe_membros`
--
ALTER TABLE `equipe_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `espiritualidades`
--
ALTER TABLE `espiritualidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `espiritualidade_presencas`
--
ALTER TABLE `espiritualidade_presencas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `missas`
--
ALTER TABLE `missas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `modalidades`
--
ALTER TABLE `modalidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `modulos_customizados`
--
ALTER TABLE `modulos_customizados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `musicas`
--
ALTER TABLE `musicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pessoas`
--
ALTER TABLE `pessoas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `pregacoes_dinamicas`
--
ALTER TABLE `pregacoes_dinamicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `recreacao`
--
ALTER TABLE `recreacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `reuniao_momentos`
--
ALTER TABLE `reuniao_momentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `reunioes`
--
ALTER TABLE `reunioes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `setores`
--
ALTER TABLE `setores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `voluntarios`
--
ALTER TABLE `voluntarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `apostilas_musicas`
--
ALTER TABLE `apostilas_musicas`
  ADD CONSTRAINT `fk_apostilas_edicoes` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `campistas_historico`
--
ALTER TABLE `campistas_historico`
  ADD CONSTRAINT `campistas_historico_ibfk_1` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoas` (`id`),
  ADD CONSTRAINT `campistas_historico_ibfk_2` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`);

--
-- Restrições para tabelas `cronograma`
--
ALTER TABLE `cronograma`
  ADD CONSTRAINT `cronograma_ibfk_1` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `edicoes`
--
ALTER TABLE `edicoes`
  ADD CONSTRAINT `edicoes_ibfk_1` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `equipes_lideres`
--
ALTER TABLE `equipes_lideres`
  ADD CONSTRAINT `equipes_lideres_ibfk_1` FOREIGN KEY (`equipe_id`) REFERENCES `equipes_campistas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `equipes_membros`
--
ALTER TABLE `equipes_membros`
  ADD CONSTRAINT `equipes_membros_ibfk_1` FOREIGN KEY (`equipe_id`) REFERENCES `equipes_campistas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `equipes_trabalho`
--
ALTER TABLE `equipes_trabalho`
  ADD CONSTRAINT `equipes_trabalho_ibfk_1` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `equipe_geral`
--
ALTER TABLE `equipe_geral`
  ADD CONSTRAINT `equipe_geral_ibfk_1` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `equipe_membros`
--
ALTER TABLE `equipe_membros`
  ADD CONSTRAINT `equipe_membros_ibfk_1` FOREIGN KEY (`equipe_id`) REFERENCES `equipes_trabalho` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipe_membros_ibfk_2` FOREIGN KEY (`voluntario_id`) REFERENCES `equipe_geral` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `missas`
--
ALTER TABLE `missas`
  ADD CONSTRAINT `missas_ibfk_1` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos_customizados`
--
ALTER TABLE `modulos_customizados`
  ADD CONSTRAINT `modulos_customizados_ibfk_1` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `musicas`
--
ALTER TABLE `musicas`
  ADD CONSTRAINT `musicas_ibfk_1` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pregacoes_dinamicas`
--
ALTER TABLE `pregacoes_dinamicas`
  ADD CONSTRAINT `fk_pregacoes_edicoes` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `reuniao_momentos`
--
ALTER TABLE `reuniao_momentos`
  ADD CONSTRAINT `reuniao_momentos_ibfk_1` FOREIGN KEY (`reuniao_id`) REFERENCES `reunioes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `reunioes`
--
ALTER TABLE `reunioes`
  ADD CONSTRAINT `reunioes_ibfk_1` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `setores`
--
ALTER TABLE `setores`
  ADD CONSTRAINT `setores_ibfk_1` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `setores_ibfk_2` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `usuario_modalidade`
--
ALTER TABLE `usuario_modalidade`
  ADD CONSTRAINT `usuario_modalidade_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_modalidade_ibfk_2` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `voluntarios`
--
ALTER TABLE `voluntarios`
  ADD CONSTRAINT `voluntarios_ibfk_1` FOREIGN KEY (`modalidade_id`) REFERENCES `modalidades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voluntarios_ibfk_2` FOREIGN KEY (`edicao_id`) REFERENCES `edicoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voluntarios_ibfk_3` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
