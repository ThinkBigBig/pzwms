<!DOCTYPE html>
<html>
<head>
    <title>Show PDF</title>
    <meta charset="utf-8" />
    <script type="text/javascript" src='pdfobject.min.js'></script>
    <style type="text/css">
        html,body,#pdf_viewer{
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <!-- <div id="pdf_viewer"></div> -->
    <iframe
  style="width:100% ;height:100%"
  title="PDF文件"
  id="Iframe"
  src={{$url}}
 ></iframe>
</body>
<script type="text/javascript">
    if(PDFObject.supportsPDFs){
        // PDF嵌入到网页
        PDFObject.embed("{{ $url }}", "#pdf_viewer" );
    } else {
        location.href = "/canvas";
    }
    
    // 还可以通过以下代码进行判断是否支持PDFObject预览
    if(PDFObject.supportsPDFs){
       console.log("Yay, this browser supports inline PDFs.");
    } else {
       console.log("Boo, inline PDFs are not supported by this browser");
    }
</script>
</html>