// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import {
  FArtglosseInterface,
  FGlossaireInterface,
} from "../../../../../interface/FArticleInterface";
import { getListObjectSageMetadata } from "../../../../../functions/getMetadata";
import {
  FormInterface,
  TableLineItemInterface,
} from "../../../../../interface/InputInterface";
import { FormContentComponent } from "../../FormContentComponent";
import {
  createFormContent,
  handleFormIsValid,
} from "../../../../../functions/form";
import { FormInput } from "../../fields/FormInput";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

const fGlossaires: FGlossaireInterface[] = JSON.parse(
  $(`[data-${TOKEN}-fglossaires]`).attr(`data-${TOKEN}-fglossaires`) ?? "[]",
);

export const ArticleGlossairesComponent = React.forwardRef((props, ref) => {
  const prefix = "fArtglosses";
  const [fArtglosses, setFArtglosses] = React.useState<FArtglosseInterface[]>(
    getListObjectSageMetadata(prefix, articleMeta, "glNo"),
  );

  const getForm = (): FormInterface => {
    return {
      content: createFormContent({
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
              headers: ["", translations.words.intitule, ""],
              removeItem: (fGlossaire: FGlossaireInterface) => {
                setFArtglosses((v) => {
                  return v.filter(
                    (fArtglosse) =>
                      fArtglosse.glNo.toString() !== fGlossaire.glNo.toString(),
                  );
                });
              },
              add: {
                table: {
                  headers: [translations.words.intitule],
                  addItem: (fGlossaire: FGlossaireInterface) => {
                    setFArtglosses((v) => {
                      return [
                        ...v,
                        {
                          glNo: fGlossaire.glNo,
                        },
                      ];
                    });
                  },
                  search: (item: FGlossaireInterface, search: string) => {
                    return (
                      item.glIntitule
                        .toLowerCase()
                        .includes(search.toLowerCase()) ||
                      item.glText.toLowerCase().includes(search.toLowerCase())
                    );
                  },
                  items: fGlossaires
                    .filter(
                      (fGlossaire) =>
                        fArtglosses.find(
                          (fArtglosse) =>
                            fArtglosse.glNo.toString() ===
                            fGlossaire.glNo.toString(),
                        ) === undefined,
                    )
                    .map((fGlossaire): TableLineItemInterface => {
                      return {
                        item: fGlossaire,
                        lines: [
                          {
                            Dom: <span>{fGlossaire.glIntitule}</span>,
                          },
                          {
                            Dom: (
                              <Tooltip title={fGlossaire.glText} arrow>
                                <span>
                                  {fGlossaire.glText.length > 102
                                    ? fGlossaire.glText.slice(0, 102) + "..."
                                    : fGlossaire.glText}
                                </span>
                              </Tooltip>
                            ),
                          },
                        ],
                      };
                    }),
                },
              },
              items: fArtglosses.map((fArtglosse): TableLineItemInterface => {
                const fGlossaire = fGlossaires.find(
                  (f) => f.glNo.toString() === fArtglosse.glNo.toString(),
                );
                return {
                  item: fGlossaire,
                  lines: [
                    {
                      field: {
                        name: prefix + "[" + fGlossaire.glNo + "].glNo",
                        DomField: FormInput,
                        type: "hidden",
                        hideLabel: true,
                        initValues: {
                          value: fGlossaire.glNo,
                        },
                      },
                    },
                    {
                      Dom: <span>{fGlossaire.glIntitule}</span>,
                    },
                    {
                      Dom: (
                        <Tooltip title={fGlossaire.glText} arrow>
                          <span>
                            {fGlossaire.glText.length > 102
                              ? fGlossaire.glText.slice(0, 102) + "..."
                              : fGlossaire.glText}
                          </span>
                        </Tooltip>
                      ),
                    },
                  ],
                };
              }),
            },
          },
        ],
      }),
    };
  };

  const [form, setForm] = React.useState<FormInterface>(getForm());

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<boolean> {
      return await handleFormIsValid(form.content);
    },
  }));

  React.useEffect(() => {
    setForm(getForm());
  }, [fArtglosses]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <FormContentComponent content={form.content} transPrefix="fArticles" />
  );
});
