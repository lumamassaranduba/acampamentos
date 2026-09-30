-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 30/09/2026 às 19:34
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
(3, 2, 'COMUNIDADE FORTALEZA - COR ROSA', '#ec41b6', '2026-09-30 16:53:20');

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
(1, 3, 'Lanna Livia Fernandes Andrade', '2026-09-30 16:52:21'),
(15, 2, 'Luma Massaranduba', '2026-09-30 17:00:34');

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
(2, 2, 'Secretaria', 'Providenciar crachás.\r\nPregação: Imagem e semelhança: Estar com as tintas preparadas, coloridas e brancas.\r\nBolsa para vivências.\r\nOrganização das pastas das vivências.\r\nBuscar frutas para as vivências com a cozinha.\r\nComprar as uvas.\r\nEntregar as uvas para a manutenção.\r\nMomento da promessa: Bexiga com as promessas dentro.\r\nMissas: Impressão das preces e leituras.\r\nMomento dos dons do Espírito Santo: Plaquinhas com significados.\r\nTalento: Caixa de presente bem enfeitada e bonita.\r\nCaixa para Bíblias por comunidade.\r\nEstar sempre preparado com vendas, papel e caneta.\r\nPregação da Bíblia: Caça ao tesouro (Lugares simples para enterrar, embalar as Bíblias para presente e organizar as pistas).\r\nMomento da salvação: Roupa de Jesus e Maria.\r\nEnfeitar a entrada da capela para o céu e o inferno.\r\nMomento dos dois caminhos: Participação no inferno.\r\nProvidenciar sacos de lixo para colocar as roupas sujas dos campistas.\r\nEntregar cartinhas no momento das cartinhas.\r\nPregação Indelével: Pedra para cada um (Lembrando que a marca de óleo não pode sair) (Ver se consegue perfumar o óleo.)\r\nIr atrás de todo o material necessário e impressora.', '2026-09-30 13:04:55');

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
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `equipe_geral`
--

INSERT INTO `equipe_geral` (`id`, `edicao_id`, `nome`, `telefone`, `tamanho_camiseta`, `criado_em`) VALUES
(1, 2, 'Sophia Christofole Rampasso', '18 99786-9866', 'PP', '2026-09-30 12:59:26'),
(2, 2, 'Elisangela Christofole', '18 99789-5455', 'GG', '2026-09-30 13:04:27'),
(3, 2, 'Kaio Gabriel Alves Figueredo', '18 99674-2013', 'M', '2026-09-30 13:04:45');

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
(1, 1, 1, 0, '2026-09-30 12:59:48'),
(2, 2, 2, 1, '2026-09-30 13:05:07'),
(3, 2, 3, 0, '2026-09-30 13:05:11');

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
(4, 'Luma Massaranduba', 'luma@ces.com', '$2y$10$LaEprcMb7Y0rJBCpOTAeF.MaXJeIt0sMYVtZe6M.bYxnWupDnequq', 'DIRETOR', NULL, 1, '2026-09-30 12:10:12'),
(5, 'natalia bosisio', 'natalia@pac.com', '$2y$10$82GxBH445pXzNeBEf5u9.OuhN/BHK2Y12fSaFM0PuZ9vr/RleHhrK', 'COORDENADOR', NULL, 1, '2026-09-30 12:13:43'),
(6, 'kaio', 'kaio@joam.com', '$2y$10$fMexLemdAf7sb2QAlcmgLermOWCuCfc5ZIgQ4JQuc3pyzuimxdhPO', 'COORDENADOR', NULL, 1, '2026-09-30 14:27:53');

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
(4, 3),
(5, 4),
(6, 5);

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

-- --------------------------------------------------------

--
-- Estrutura para tabela `voluntarios_setores`
--

CREATE TABLE `voluntarios_setores` (
  `voluntario_id` int(11) NOT NULL,
  `setor_id` int(11) NOT NULL,
  `eh_coordenador` tinyint(1) DEFAULT 0
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
-- Índices de tabela `pregacoes_dinamicas`
--
ALTER TABLE `pregacoes_dinamicas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pregacoes_edicoes` (`edicao_id`);

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
-- Índices de tabela `voluntarios_setores`
--
ALTER TABLE `voluntarios_setores`
  ADD PRIMARY KEY (`voluntario_id`,`setor_id`),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `equipes_lideres`
--
ALTER TABLE `equipes_lideres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `equipes_membros`
--
ALTER TABLE `equipes_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `equipes_trabalho`
--
ALTER TABLE `equipes_trabalho`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `equipe_geral`
--
ALTER TABLE `equipe_geral`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `equipe_membros`
--
ALTER TABLE `equipe_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT de tabela `pregacoes_dinamicas`
--
ALTER TABLE `pregacoes_dinamicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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

--
-- Restrições para tabelas `voluntarios_setores`
--
ALTER TABLE `voluntarios_setores`
  ADD CONSTRAINT `voluntarios_setores_ibfk_1` FOREIGN KEY (`voluntario_id`) REFERENCES `voluntarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voluntarios_setores_ibfk_2` FOREIGN KEY (`setor_id`) REFERENCES `setores` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
