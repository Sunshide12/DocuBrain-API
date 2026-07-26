<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>GraphiQL — DocuBrain</title>
    <style>
        body {
            margin: 0;
            overflow: hidden;
        }
        #graphiql {
            height: 100dvh;
        }
        #graphiql-loading {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }
        .docExplorerWrap {
            overflow: auto !important;
        }
    </style>
    {{-- Assets served locally — no CDN dependency --}}
    <script src="/graphiql-assets/react.js"></script>
    <script src="/graphiql-assets/react-dom.js"></script>
    <link rel="stylesheet" href="/graphiql-assets/graphiql.css"/>
    <link rel="stylesheet" href="/graphiql-assets/explorer.css"/>
</head>
<body>

<div id="graphiql">
    <div id="graphiql-loading">Loading…</div>
</div>

<script src="/graphiql-assets/graphiql.js"></script>
<script src="/graphiql-assets/explorer.js"></script>
<script>
    const fetcher = GraphiQL.createFetcher({
        url: '/graphql',
        subscriptionUrl: '',
    });
    const explorer = GraphiQLPluginExplorer.explorerPlugin();

    function GraphiQLWithExplorer() {
        return React.createElement(GraphiQL, {
            fetcher,
            plugins: [explorer],
        });
    }

    ReactDOM.render(
        React.createElement(GraphiQLWithExplorer),
        document.getElementById('graphiql'),
    );
</script>

</body>
</html>
