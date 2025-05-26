// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent, useImperativeHandle } from "react";
import {
  FormContentInterface,
  FormInterface,
  TableLineItemInterface,
} from "../../../interface/InputInterface";
import {
  DefaultFormState,
  getDefaultValue,
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
} from "../../../functions/form";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { getListObjectSageMetadata } from "../../../functions/getMetadata";
import { getTranslations } from "../../../functions/translations";
import { FormInput } from "../../component/form/FormInput";
import { FArticleClientInterface } from "../../../interface/FArticleInterface";
import { AcPrixVenInput } from "../../component/form/fArticle/AcPrixVenInput";
import { ArticlePricesComponent } from "../ArticlePricesComponent";
import { MetadataInterface } from "../../../interface/WordpressInterface";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const pCattarifs: any[] = JSON.parse(
  $("[data-sage-pcattarifs]").attr("data-sage-pcattarifs") ?? "[]",
);

export const ArticleCatTarifComponent = React.forwardRef((props, ref) => {
  const handleChange =
    (prop: keyof DefaultFormState) =>
    (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof DefaultFormState) =>
    (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };
  const prefix = "fArtclients";
  const [fArtclients] = React.useState<FArticleClientInterface[]>(
    getListObjectSageMetadata(prefix, articleMeta, "acCategorie"),
  );
  const [acPrixVenRefs, setAcPrixVenRefs] = React.useState<any[]>([]);

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
                const refAcPrixVen = React.createRef();
                setAcPrixVenRefs((x) => [...x, refAcPrixVen]);
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
                      },
                    },
                    {
                      Dom: (
                        <AcPrixVenInput
                          defaultValue={fArtclient.acPrixVen}
                          acCategorie={fArtclient.acCategorie}
                          ref={refAcPrixVen}
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
    const flatFields = getFlatFields(formContent);
    return {
      content: formContent,
      flatFields: flatFields,
      fieldNames: flatFields.map((f) => f.name),
    };
  });

  const [values, setValues] = React.useState(getDefaultValue(form));
  const [arPrixAch, setArPrixAch] = React.useState<string>("0");

  useImperativeHandle(ref, () => ({
    onParentFormChange(v: any): void {
      setArPrixAch(v.arPrixAch.value);
    },
  }));

  React.useEffect(() => {
    for (const acPrixVenRef of acPrixVenRefs) {
      acPrixVenRef.current.onParentFormChange({
        arPrixAch,
        ...values,
      });
    }
  }, [values, arPrixAch]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <FormContentComponent
      content={form.content}
      values={values}
      handleChange={handleChange}
      transPrefix="fArticles"
      handleChangeSelect={handleChangeSelect}
    />
  );
});
