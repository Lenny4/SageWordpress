// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import {
  FormContentInterface,
  FormInterface,
  TableLineItemInterface,
  TriggerFormContentChanged,
} from "../../../interface/InputInterface";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { getListObjectSageMetadata } from "../../../functions/getMetadata";
import { getTranslations } from "../../../functions/translations";
import { FormInput } from "../../component/form/FormInput";
import { FArticleClientInterface } from "../../../interface/FArticleInterface";
import { AcPrixVenInput } from "../../component/form/fArticle/AcPrixVenInput";
import { ArticlePricesComponent } from "../ArticlePricesComponent";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import {
  getDomsToSetParentFormData,
  getKeyFromName,
} from "../../../functions/form";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const pCattarifs: any[] = JSON.parse(
  $("[data-sage-pcattarifs]").attr("data-sage-pcattarifs") ?? "[]",
);

export const ArticleCatTarifComponent = React.forwardRef((props, ref) => {
  const prefix = "fArtclients";
  const [fArtclients] = React.useState<FArticleClientInterface[]>(
    getListObjectSageMetadata(prefix, articleMeta, "acCategorie"),
  );

  const onAcCoefChanged: TriggerFormContentChanged = (name, newValue) => {
    const doms = getDomsToSetParentFormData(form.content);
    for (const dom of doms) {
      if (dom.ref.current?.onAcCoefChanged) {
        dom.ref.current?.onAcCoefChanged(
          Number(newValue),
          getKeyFromName(name),
        );
      }
    }
  };

  const [form] = React.useState<FormInterface>(() => {
    const formContent: FormContentInterface[] = [
      {
        props: {
          container: true,
          spacing: 1,
          sx: { p: 1 },
        },
        children: [
          {
            props: {
              size: { xs: 12 },
            },
            table: {
              headers: [
                translations.words.category,
                translations.words.coefficient,
                translations.words.sellPrice,
                translations.words.discount,
              ],
              items: fArtclients.map((fArtclient): TableLineItemInterface => {
                return {
                  item: fArtclient,
                  lines: [
                    {
                      Dom: <>{pCattarifs[fArtclient.acCategorie].ctIntitule}</>,
                    },
                    {
                      field: {
                        name:
                          prefix + "[" + fArtclient.acCategorie + "].acCoef",
                        DomField: FormInput,
                        type: "number",
                        hideLabel: true,
                        triggerFormContentChanged: onAcCoefChanged,
                        initValues: { value: fArtclient.acCoef },
                      },
                    },
                    {
                      Dom: (
                        <AcPrixVenInput
                          defaultValue={fArtclient.acPrixVen}
                          acCategorie={fArtclient.acCategorie}
                          ref={React.createRef()}
                        />
                      ),
                    },
                    {
                      field: {
                        name:
                          prefix + "[" + fArtclient.acCategorie + "].acRemise",
                        DomField: FormInput,
                        type: "number",
                        hideLabel: true,
                        initValues: { value: fArtclient.acRemise },
                      },
                    },
                  ],
                };
              }),
            },
          },
          {
            props: {
              size: { xs: 12 },
            },
            Dom: <ArticlePricesComponent />,
          },
        ],
      },
    ];
    return {
      content: formContent,
    };
  });

  useImperativeHandle(ref, () => ({
    getForm() {
      return form;
    },
  }));

  return (
    <FormContentComponent content={form.content} transPrefix="fArticles" />
  );
});
